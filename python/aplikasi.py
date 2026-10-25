"""
APLIKASI OTOMASI GAJI DPP - antarmuka seret-dan-lepas di peramban.

Menjalankan server kecil di komputer ini saja (127.0.0.1), lalu membuka peramban. Juru bayar
cukup menyeret PDF DPP dan berkas pembukuan ke satu kotak, tekan Proses, lalu mengunduh hasilnya.
Data personel (acuan pencocokan DPP) dikelola di halaman Data Personel dan disimpan permanen di
data/personel.db (personel_db.py).

Antarmukanya SIPAGA (folder sipaga/): beberapa halaman - proses gaji bertahap, dasbor, slip,
data personel, rekap - yang semuanya membaca hasil proses sesinya lewat /data. Antarmuka satu
halaman yang lama (antarmuka.html) tetap ada di /lama.

Tidak ada data yang keluar dari komputer ini: server hanya mendengar di alamat lokal, dan tidak
ada satu pun permintaan ke internet. Karena database personel bisa dibaca dan diubah lewat HTTP,
setiap permintaan harus menyebut Host 127.0.0.1/localhost (menangkal DNS rebinding), dan permintaan
yang mengubah data personel wajib membawa kepala X-SIPAGA (menangkal kiriman dari situs lain). Berkas yang diunggah disimpan di folder sementara milik
sesi itu saja, dan dihapus saat aplikasi ditutup dengan Ctrl+C. Kalau jendelanya ditutup lewat
tombol X, folder itu tertinggal; sisa yang sudah lebih dari sehari dibersihkan saat aplikasi
dinyalakan lagi (bersihkan_sisa_lama).

Hanya memakai pustaka bawaan Python. Logika pemrosesannya persis sama dengan baris perintah:
keduanya memanggil jalankan.proses().

Pemakaian:  python aplikasi.py        (atau klik dua kali 3_BUKA_APLIKASI.bat)
"""
import json
import re
import secrets
import shutil
import socket
import sys
import tempfile
import threading
import time
import traceback
import webbrowser
from datetime import datetime
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import parse_qs, unquote, urlparse

from dpp_parser import KOLOM_ANGKA, POTONGAN, TUNJANGAN
from jalankan import proses, siapkan_template
from laporan import LABEL_ANGKA
import personel_db
import potongan

DASAR = Path(__file__).resolve().parent
SIPAGA = DASAR / "sipaga"
ANTARMUKA = DASAR / "antarmuka.html"
TEMPLATE_BUKU = DASAR / "TEMPLATE_PEMBUKUAN.xlsx"
PORT_AWAL = 8713
BATAS_BERKAS = 80 * 1024 * 1024        # 80 MB per berkas, jauh di atas ukuran DPP biasa
BATAS_JUMLAH = 40                      # jumlah berkas maksimum per sesi
AWALAN_SESI = "dpp_gaji_"              # nama folder sementara tiap sesi
BATAS_JSON = 256 * 1024               # isi permintaan JSON (form personel) paling besar
UMUR_SISA = 24 * 3600                  # folder sesi yang diam selama ini dianggap sisa, bukan milik aplikasi lain
TIPE_STATIS = {".html": "text/html; charset=utf-8", ".css": "text/css; charset=utf-8",
               ".js": "text/javascript; charset=utf-8"}
RE_STATIS = re.compile(r"[A-Za-z0-9][A-Za-z0-9_-]*\.(?:html|css|js)")
HALAMAN_AWAL = "2-proses-gaji.html"

sesi_aktif = {}                        # id -> {"dir": Path, "berkas": {nama: Path}, "data": dict}
kunci = threading.Lock()
rencana_massal = {}                    # token -> rencana potongan massal yang menunggu diterapkan


def nama_aman(nama: str) -> str:
    """Ambil nama berkasnya saja, buang komponen folder dan karakter yang tidak diinginkan."""
    nama = unquote(nama or "").replace("\\", "/").split("/")[-1]
    nama = "".join(c for c in nama if c.isalnum() or c in " ._-()").strip()
    if len(nama) > 120:                    # dipotong di badannya; akhirannya yang menentukan jenis berkas
        akhiran = Path(nama).suffix[:10]
        nama = nama[:120 - len(akhiran)] + akhiran
    return nama or "tanpa_nama"


def buat_sesi():
    sid = secrets.token_urlsafe(16)
    d = Path(tempfile.mkdtemp(prefix=AWALAN_SESI))
    (d / "masuk").mkdir()
    (d / "hasil").mkdir()
    with kunci:
        sesi_aktif[sid] = {"dir": d, "berkas": {}, "data": None}
    return sid


def hapus_sesi(sid):
    with kunci:
        s = sesi_aktif.pop(sid, None)
    if s:
        shutil.rmtree(s["dir"], ignore_errors=True)


def berkas_statis(nama: str):
    """Berkas antarmuka di folder sipaga/. Hanya nama polos .html/.css/.js, tanpa folder."""
    if not RE_STATIS.fullmatch(nama):
        return None
    p = SIPAGA / nama
    return p if p.is_file() else None


def bersihkan_semua():
    for sid in list(sesi_aktif):
        hapus_sesi(sid)


def bersihkan_sisa_lama() -> int:
    """Hapus folder sesi yang tertinggal dari pemakaian sebelumnya; kembalikan jumlahnya.

    bersihkan_semua() hanya jalan bila aplikasi ditutup dengan Ctrl+C. Menutup jendela lewat
    tombol X mematikan prosesnya begitu saja, sehingga salinan PDF dan pembukuan tertinggal di
    folder sementara. Yang dihapus hanya folder yang isinya sudah diam lebih dari UMUR_SISA,
    supaya aplikasi lain yang sedang dipakai di port lain tidak kehilangan sesinya.
    """
    batas, n = time.time() - UMUR_SISA, 0
    for d in Path(tempfile.gettempdir()).glob(AWALAN_SESI + "*"):
        try:
            if not d.is_dir() or max(p.stat().st_mtime for p in (d, *d.rglob("*"))) >= batas:
                continue
        except OSError:
            continue
        shutil.rmtree(d, ignore_errors=True)
        n += not d.exists()
    return n


def ringkas(h) -> dict:
    """Ubah hasil proses() menjadi data yang bisa ditampilkan antarmuka."""
    dpp = [{"file": x["file"], "bulan": x["bulan"], "anggota": len(x["anggota"]),
            "konsisten": sum(1 for a in x["anggota"] if not a["masalah"]),
            "lembar_cocok": sum(l["cocok"] for l in x["lembar"]), "lembar": len(x["lembar"]),
            "galat": len(x["galat"]), "scan": x.get("halaman_scan", 0),
            "dibayarkan": sum(a["dibayarkan"] for a in x["anggota"])}
           for x in h["dpp_files"]]
    perubahan = [{"baris": b["baris"], "no": b["no"], "nama": b["nama"], "pangkat": b["pangkat"],
                  "nrp": b["nrp_asli"], "lama": round(b["gaji_lama"]), "baru": d["dibayarkan"]}
                 for b, d, _ in sorted(h["pasangan"], key=lambda x: x[0]["baris"])
                 if round(b["gaji_lama"]) != d["dibayarkan"]]
    s = h["statistik"]
    return {
        "bulan": h["bulan_baru"], "dpp": dpp, "statistik": s, "tingkat": h["tingkat"],
        "perubahan": perubahan,
        "temuan": [{k: t[k] for k in ("tingkat", "kategori", "baris", "nrp", "nama", "detail")}
                   for t in h["temuan"]],
        "rekonsiliasi": {"update": s["total_update"], "dpp": s["total_dpp_cocok"],
                         "cocok": s["total_update"] == s["total_dpp_cocok"]},
        "pencocokan": h.get("pencocokan"),
        "satuan": ringkas_potongan(h),
    }


def ringkas_potongan(h):
    """Ringkasan Daftar Potongan untuk antarmuka; None bila proses ini tanpa potongan."""
    p = h.get("potongan")
    if not p:
        return None
    tung = [{"nama": x["nama"], "nrp": x["nrp_asli"], "sheet": k["sheet"], "baris": x.get("baris"),
             "gaji": x["gaji"], **x["kop"]}
            for k in p["kelompok"] for x in k["baris"] if x["kop"] and x["kop"]["tunggakan"]]
    return {"bulan": p["bulan"], "periode": p["periode"], "bank": p["bank"], "ringkas": p["ringkas"],
            "peringatan": p["peringatan"], "tunggakan": tung, "arisan": p.get("arisan"),
            "berkas": Path(h["out_pot"]).name, "sisa_ybs": p["aturan"]["sisa_ybs"]}


def rinci_potongan(h):
    """Baris per orang Daftar Potongan untuk halaman Potongan."""
    p = h.get("potongan")
    if not p:
        return []
    return [{"sheet": k["sheet"], "jenis": k["jenis"], "baris": x.get("baris"), "nama": x["nama"],
             "nrp": x["nrp_asli"], "pangkat": x.get("pangkat") or "", "pid": x["pid"], "gaji": x["gaji"],
             "total": x["total"], "ybs": x["ybs"], "tunggakan": x["kop"]["tunggakan"] if x["kop"] else 0,
             "mass_debet": x["mass_debet"], "ket": list(dict.fromkeys(x["ket"])), "potongan": x["potongan"],
             "pinjaman": x["pinjaman"]}
            for k in p["kelompok"] for x in k["baris"]]


def rinci(h) -> dict:
    """Data per orang untuk halaman slip, data personel, dan rekap di antarmuka SIPAGA."""
    pasangan = {id(d): (b, cara) for b, d, cara in h["pasangan"]}
    personel, nomor = [], {}
    for x in h["dpp_files"]:
        for a in x["anggota"]:
            b, cara = pasangan.get(id(a), (None, None))
            ocr = a.get("ocr") or {}
            nomor[id(a)] = len(personel) + 1
            personel.append({
                "n": len(personel) + 1, "file": a["file"], "halaman": a["halaman"],
                "no_urut": a["no_urut"],
                **{k: a.get(k, "") for k in ("nama", "nrp", "korps", "pangkat", "jabatan", "sts_kawin")},
                **{k: a[k] for k in KOLOM_ANGKA},
                "masalah": a["masalah"], "baris": b["baris"] if b else None, "cara": cara,
                "gaji_lama": round(b["gaji_lama"]) if b else None,
                "scan": bool(ocr), "dihitung": ocr.get("dihitung", []), "ragu": ocr.get("ragu", [])})
    per_baris = {b["baris"]: (d, cara) for b, d, cara in h["pasangan"]}
    buku = []
    for b in h["buku"]["anggota"]:
        d, cara = per_baris.get(b["baris"], (None, None))
        buku.append({"baris": b["baris"], "no": b["no"], "nama": b["nama"], "pangkat": b["pangkat"],
                     "nrp": b["nrp_asli"], "gaji_lama": round(b["gaji_lama"]),
                     "gaji_baru": d["dibayarkan"] if d else None,
                     "n": nomor[id(d)] if d else None, "cara": cara})
    return {"personel": personel, "buku": buku, "kolom": KOLOM_ANGKA, "tunjangan": TUNJANGAN,
            "potongan": POTONGAN, "label": LABEL_ANGKA,
            "kosong": any(cara == "PDF" for _, _, cara in h["pasangan"]),
            "pot_baris": rinci_potongan(h)}


class Penangan(BaseHTTPRequestHandler):
    server_version = "OtomasiGajiDPP/1.0"
    protocol_version = "HTTP/1.1"

    def log_message(self, *a):            # jangan ramaikan jendela konsol juru bayar
        pass

    # ---------------------------------------------------------------- pembantu balasan
    def kirim(self, kode, isi: bytes, tipe="application/json; charset=utf-8", tambahan=()):
        self.send_response(kode)
        self.send_header("Content-Type", tipe)
        self.send_header("Content-Length", str(len(isi)))
        self.send_header("Cache-Control", "no-store")
        for k, v in tambahan:
            self.send_header(k, v)
        self.end_headers()
        self.wfile.write(isi)

    def kirim_json(self, data, kode=200):
        self.kirim(kode, json.dumps(data, ensure_ascii=False, default=str).encode("utf-8"))

    def galat(self, pesan, kode=400):
        self.kirim_json({"ok": False, "pesan": pesan}, kode)

    def kirim_lama(self):
        try:
            isi = ANTARMUKA.read_bytes()
        except OSError:
            return self.galat(f"Berkas antarmuka tidak ditemukan: {ANTARMUKA}", 500)
        return self.kirim(200, isi, "text/html; charset=utf-8")

    def baca_json(self):
        try:
            panjang = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            panjang = -1
        if not 0 <= panjang <= BATAS_JSON:
            self.close_connection = True       # body tidak dibaca; sambungan ditutup
            return None
        try:
            return json.loads(self.rfile.read(panjang) or b"{}")
        except ValueError:
            return None

    def personel_get(self, path, q):
        return _personel_get(self, path, q)

    def personel_post(self, path, q):
        return _personel_post(self, path, q)

    def ambil_sesi(self, q):
        sid = (q.get("sesi") or [""])[0]
        with kunci:
            s = sesi_aktif.get(sid)
        return sid, s

    # ---------------------------------------------------------------- GET
    def host_sah(self):
        port = self.server.server_address[1]
        return self.headers.get("Host", "") in (f"127.0.0.1:{port}", f"localhost:{port}")

    def do_GET(self):
        u = urlparse(self.path)
        q = parse_qs(u.query)
        if not self.host_sah():
            return self.galat("Buka aplikasi lewat http://127.0.0.1 atau http://localhost.", 403)
        if u.path.startswith("/personel"):
            return self.personel_get(u.path, q)
        if u.path == "/potongan" or u.path.startswith("/potongan/"):
            return _potongan_get(self, u.path, q)
        if u.path in ("/", "/index.html"):
            # Aplikasi selalu dibuka tanpa data, jadi yang pertama tampil halaman proses, bukan dasbor.
            if not berkas_statis(HALAMAN_AWAL):    # folder sipaga/ tidak ikut tersalin
                return self.kirim_lama()
            return self.kirim(302, b"", "text/plain; charset=utf-8",
                              [("Location", "/" + HALAMAN_AWAL)])
        if u.path == "/lama":
            return self.kirim_lama()
        if u.path == "/data":
            # Dijaga id sesi seperti /unduh: tanpa id yang benar tidak ada data yang keluar.
            _, s = self.ambil_sesi(q)
            return self.kirim_json((s and s["data"]) or {"ok": True, "ada": False})
        statis = berkas_statis(u.path[1:])
        if statis:
            return self.kirim(200, statis.read_bytes(), TIPE_STATIS[statis.suffix])
        if u.path == "/template":
            if not TEMPLATE_BUKU.is_file():
                return self.galat(
                    "TEMPLATE_PEMBUKUAN.xlsx belum ada. Jalankan "
                    "'python buat_template_pembukuan.py' sekali untuk membuatnya.", 404)
            return self.kirim(
                200, TEMPLATE_BUKU.read_bytes(),
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                [("Content-Disposition", f'attachment; filename="{TEMPLATE_BUKU.name}"')])
        if u.path == "/unduh":
            sid, s = self.ambil_sesi(q)
            if not s:
                return self.galat("Sesi sudah berakhir. Muat ulang halaman dan proses lagi.", 404)
            nama = (q.get("berkas") or [""])[0]
            jalur = s["berkas"].get(nama)
            if not jalur or not Path(jalur).is_file():
                return self.galat("Berkas hasil tidak ada di sesi ini.", 404)
            isi = Path(jalur).read_bytes()
            return self.kirim(
                200, isi,
                "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
                [("Content-Disposition", f'attachment; filename="{nama}"')])
        self.galat("Alamat tidak dikenal.", 404)

    # ---------------------------------------------------------------- POST
    def buang_body(self):
        """Alamat selain /unggah tidak memakai body. Kalau ada yang mengirimnya, body itu harus
        dihabiskan dulu; kalau tidak, sisanya terbaca sebagai permintaan berikutnya."""
        try:
            panjang = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            panjang = -1
        if 0 < panjang <= 1024 * 1024:
            self.rfile.read(panjang)
        elif panjang:
            self.close_connection = True

    def do_POST(self):
        u = urlparse(self.path)
        q = parse_qs(u.query)
        if not self.host_sah():
            self.close_connection = True       # body tidak dibaca; sambungan ditutup
            return self.galat("Buka aplikasi lewat http://127.0.0.1 atau http://localhost.", 403)
        try:
            if u.path == "/unggah":
                return self.unggah(q)
            if u.path.startswith("/personel/"):
                return self.personel_post(u.path, q)
            if u.path.startswith("/potongan/"):
                return _potongan_post(self, u.path, q)
            self.buang_body()
            if u.path == "/sesi":
                return self.kirim_json({"ok": True, "sesi": buat_sesi()})
            if u.path == "/proses":
                return self.jalankan(q)
            if u.path == "/tutup":
                sid, _ = self.ambil_sesi(q)
                hapus_sesi(sid)
                return self.kirim_json({"ok": True})
        except Exception:                                      # noqa: BLE001
            traceback.print_exc()
            return self.galat("Terjadi kesalahan tak terduga. Lihat jendela konsol aplikasi.", 500)
        self.galat("Alamat tidak dikenal.", 404)

    def unggah(self, q):
        try:
            panjang = int(self.headers.get("Content-Length") or 0)
        except ValueError:
            self.close_connection = True
            return self.galat("Ukuran berkas di permintaan tidak sah.")
        if panjang <= 0:
            return self.galat("Berkas kosong.")
        if panjang > BATAS_BERKAS:
            # Body-nya sengaja tidak dibaca; sambungan ditutup supaya sisa byte tidak
            # terbaca sebagai permintaan berikutnya.
            self.close_connection = True
            return self.galat(f"Berkas lebih besar dari {BATAS_BERKAS // 1024 // 1024} MB.",
                              413)
        # Body SELALU dihabiskan sebelum balasan apa pun, termasuk balasan penolakan:
        # kalau tidak, sisanya akan terbaca sebagai permintaan berikutnya di sambungan
        # keep-alive yang sama.
        data = self.rfile.read(panjang)

        sid, s = self.ambil_sesi(q)
        if not s:
            return self.galat("Sesi sudah berakhir. Muat ulang halaman.", 404)
        masuk = s["dir"] / "masuk"
        if len(list(masuk.iterdir())) >= BATAS_JUMLAH:
            return self.galat(f"Maksimum {BATAS_JUMLAH} berkas per proses.")
        nama = nama_aman(self.headers.get("X-Nama-Berkas", ""))
        if not nama.lower().endswith((".pdf", ".xlsx", ".xls")):
            return self.galat(f"{nama}: hanya .pdf, .xlsx, dan .xls yang diterima.")
        if (masuk / nama).exists():
            # Menimpa diam-diam berarti satu berkas hilang dari proses tanpa ada yang tahu.
            return self.galat(f"{nama}: sudah ada berkas bernama sama di proses ini, dan keduanya "
                              "akan saling menimpa. Ganti nama salah satunya, lalu masukkan lagi.")
        (masuk / nama).write_bytes(data)
        return self.kirim_json({"ok": True, "nama": nama, "ukuran": len(data)})

    def jalankan(self, q):
        sid, s = self.ambil_sesi(q)
        if not s:
            return self.galat("Sesi sudah berakhir. Muat ulang halaman.", 404)
        masuk, hasil = s["dir"] / "masuk", s["dir"] / "hasil"
        pdfs = sorted(p for p in masuk.iterdir() if p.suffix.lower() == ".pdf")
        buku = sorted(p for p in masuk.iterdir() if p.suffix.lower() in (".xlsx", ".xls"))
        if not pdfs:
            return self.galat("Belum ada berkas PDF DPP. Seret minimal satu PDF DPP bulan ini.")
        # SIPAGA selalu mencocokkan dengan database personel, dan database kosong berarti ditolak
        # (keputusan juru bayar, 16 Okt 2026). Antarmuka lama dan baris perintah tidak.
        acuan = potret = None
        if (q.get("personel") or [""])[0] == "wajib":
            acuan = personel_db.acuan("TNI")
            if not any(o["status"] == "aktif" for o in acuan["orang"]):
                return self.galat("Data personel masih kosong. Isi dulu lewat Data Personel > Impor, "
                                  "lalu proses lagi.")
            # Daftar Potongan ikut dibuat di jalur SIPAGA (keputusan 16-17 Okt 2026). /lama dan baris
            # perintah tidak, supaya keluarannya tetap sama.
            potret = potongan.snapshot()
        if len(buku) != 1:
            return self.galat(
                "Perlu tepat 1 berkas pembukuan (.xlsx). "
                + (f"Sekarang ada {len(buku)}: " + ", ".join(p.name for p in buku)
                   if buku else "Sekarang belum ada."))

        shutil.rmtree(hasil, ignore_errors=True)
        hasil.mkdir()
        with kunci:                        # hasil lama baru saja dihapus, jadi datanya ikut tidak berlaku
            s["berkas"], s["data"] = {}, None
        catatan = []

        def log(baris):
            catatan.append(str(baris))
            print(baris, flush=True)

        bulan = (q.get("bulan") or [""])[0].strip() or None
        try:
            tpl = siapkan_template(log=log)
            h = proses(pdfs, buku[0], hasil, bulan=bulan, tpl=tpl, log=log, personel=acuan, potongan=potret)
        except SystemExit as e:
            return self.galat(str(e.code if e.code is not None else e))
        except Exception as e:                                 # noqa: BLE001
            traceback.print_exc()
            return self.galat(f"Gagal memproses: {type(e).__name__}: {e}")

        with kunci:
            s["berkas"] = {p.name: p for p in sorted(hasil.iterdir())}
            daftar = [{"nama": p.name, "ukuran": p.stat().st_size}
                      for p in sorted(hasil.iterdir())]
        data = ringkas(h)
        data.update({"ok": True, "log": catatan, "berkas": daftar,
                     "sumber": buku[0].name, "pdf": [p.name for p in pdfs]})
        lengkap = {**data, **rinci(h), "ada": True, "sesi": sid,
                   "waktu": f"{datetime.now():%d-%m-%Y %H:%M}"}
        with kunci:
            s["data"] = lengkap
            # Gaji per personel bulan ini: dipakai cek batas pinjam di halaman potongan per orang.
            s["gaji"] = (h["potongan"] or {}).get("gaji", {})
            s["periode"] = (h["potongan"] or {}).get("periode", "")
        return self.kirim_json(data)


def _berkas_impor(s):
    # .xls yang sudah dikonversi meninggalkan .xlsx kembarannya di folder yang sama; yang dipakai
    # berkas aslinya, supaya pratinjau kedua di sesi yang sama tidak membaca buku itu dua kali.
    semua = [p for p in (s["dir"] / "masuk").iterdir() if p.suffix.lower() in (".xlsx", ".xls")]
    return sorted(p for p in semua if not (p.suffix.lower() == ".xlsx" and p.with_suffix(".xls").exists()))


def _personel_get(h, path, q):
    if path == "/personel":
        return h.kirim_json({"ok": True, "personel": personel_db.daftar(), "ringkas": personel_db.ringkas()})
    if path == "/personel/riwayat":
        pid = (q.get("id") or [""])[0]
        return h.kirim_json({"ok": True, "riwayat": personel_db.riwayat(int(pid) if pid.isdigit() else None)})
    return h.galat("Alamat tidak dikenal.", 404)


def _personel_post(h, path, q):
    data = h.baca_json()               # selalu dibaca dulu: sisa body tidak boleh jadi permintaan berikutnya
    if h.headers.get("X-SIPAGA") != "1":
        return h.galat("Permintaan ini hanya diterima dari halaman SIPAGA.", 403)
    if not isinstance(data, dict):
        return h.galat("Isi permintaan tidak sah.")
    op = str(data.get("operator") or "")
    if path == "/personel/simpan":
        pid = data.get("id")
        return h.kirim_json(personel_db.simpan(data.get("data") or {}, op, int(pid) if pid else None))
    if path == "/personel/hapus":
        return h.kirim_json(personel_db.hapus(int(data.get("id") or 0), op))
    if path not in ("/personel/impor/rencana", "/personel/impor/terapkan"):
        return h.galat("Alamat tidak dikenal.", 404)
    _, s = h.ambil_sesi(q)
    if not s:
        return h.galat("Sesi sudah berakhir. Muat ulang halaman.", 404)
    if path.endswith("/rencana"):
        pembuat = {"nominatif": personel_db.rencana_nominatif, "bank": personel_db.rencana_bank,
                   "potongan": potongan.rencana_buku, "arisan": potongan.rencana_arisan}.get(data.get("jenis"))
        if not pembuat:
            return h.galat("Jenis impor harus nominatif, bank, potongan, atau arisan.")
        berkas = _berkas_impor(s)
        if not berkas:
            return h.galat("Belum ada berkas Excel yang diunggah.")
        try:
            r = pembuat(berkas)
        except SystemExit as e:
            return h.galat(str(e.code if e.code is not None else e))
        with kunci:
            s["rencana"] = r
        return h.kirim_json({"ok": True, "rencana": r})
    with kunci:
        r = s.get("rencana")
    if not r:
        return h.galat("Belum ada pratinjau. Buat pratinjau dulu.")
    hasil = (potongan.terapkan if r["jenis_impor"] in ("potongan", "arisan") else personel_db.terapkan)(r, op)
    if hasil["ok"]:
        with kunci:
            s["rencana"] = None
    return h.kirim_json(hasil)


def _angka_id(v):
    try:
        return int(v)
    except (TypeError, ValueError):
        return None


def _gaji_sesi(sid, pid):
    """Gaji DPP bulan ini untuk satu personel dari hasil proses sesi itu (untuk cek batas pinjam)."""
    with kunci:
        s = sesi_aktif.get(sid or "")
        if not s or not s.get("gaji"):
            return None, None
        return s["gaji"].get(pid), s.get("periode")


def _potongan_get(h, path, q):
    if path == "/potongan":
        return h.kirim_json({"ok": True, **potongan.ringkas()})
    if path == "/potongan/orang":
        pid = _angka_id((q.get("id") or [""])[0])
        if pid is None:
            return h.galat("Personel tidak dikenal.")
        gaji, p = _gaji_sesi((q.get("sesi") or [""])[0], pid)
        return h.kirim_json(potongan.data_orang(pid, gaji, p))
    return h.galat("Alamat tidak dikenal.", 404)


def _potongan_post(h, path, q):
    data = h.baca_json()               # selalu dibaca dulu: sisa body tidak boleh jadi permintaan berikutnya
    if h.headers.get("X-SIPAGA") != "1":
        return h.galat("Permintaan ini hanya diterima dari halaman SIPAGA.", 403)
    if not isinstance(data, dict):
        return h.galat("Isi permintaan tidak sah.")
    op = str(data.get("operator") or "")
    if path == "/potongan/orang":
        pid = _angka_id(data.get("id"))
        if pid is None:
            return h.galat("Personel tidak dikenal.")
        return h.kirim_json(potongan.simpan_orang(pid, data.get("nilai") or {}, op))
    if path == "/potongan/pinjaman":
        isi = data.get("data") or {}
        pid = _angka_id(isi.get("personel_id"))
        gaji, p = _gaji_sesi(data.get("sesi"), pid) if pid is not None else (None, None)
        return h.kirim_json(potongan.simpan_pinjaman(isi, op, _angka_id(data.get("id")), gaji, p))
    if path == "/potongan/pokok":
        pid = _angka_id(data.get("id"))
        if pid is None:
            return h.galat("Personel tidak dikenal.")
        return h.kirim_json(potongan.simpan_pokok(pid, data.get("bulan"), data.get("nominal"), op))
    if path == "/potongan/aturan":
        return h.kirim_json(potongan.simpan_aturan(data.get("data") or {}, op))
    if path == "/potongan/massal/rencana":
        r = potongan.rencana_massal(data)
        if r["ok"]:
            token = secrets.token_urlsafe(12)
            with kunci:
                rencana_massal.clear()     # hanya pratinjau terakhir yang berlaku
                rencana_massal[token] = r["rencana"]
            r["token"] = token
        return h.kirim_json(r)
    if path == "/potongan/massal/terapkan":
        with kunci:
            r = rencana_massal.get(str(data.get("token") or ""))
        if not r:
            return h.galat("Pratinjau sudah tidak berlaku. Buat pratinjau ulang.")
        hasil = potongan.terapkan(r, op)
        if hasil["ok"]:
            with kunci:
                rencana_massal.clear()
        return h.kirim_json(hasil)
    return h.galat("Alamat tidak dikenal.", 404)


def port_bebas(awal=PORT_AWAL):
    for p in range(awal, awal + 40):
        with socket.socket() as s:
            if s.connect_ex(("127.0.0.1", p)) != 0:
                return p
    raise SystemExit("Tidak ada port bebas antara "
                     f"{awal} dan {awal + 39}. Tutup aplikasi lain lalu coba lagi.")


def main():
    """Jalankan server. Flag hanya untuk pengujian: --port N, --tanpa-peramban, --db BERKAS."""
    if not berkas_statis("sipaga.js") and not ANTARMUKA.is_file():
        raise SystemExit("Antarmuka tidak ditemukan: folder sipaga/ maupun antarmuka.html tidak ada "
                         f"di sebelah aplikasi.py ({DASAR}).")
    arg = sys.argv[1:]
    paksa_port = int(arg[arg.index("--port") + 1]) if "--port" in arg else None
    buka = "--tanpa-peramban" not in arg
    if "--db" in arg:                      # hanya untuk pengujian: database personel sementara
        personel_db.atur_path(arg[arg.index("--db") + 1])

    port = paksa_port or port_bebas()
    alamat = f"http://127.0.0.1:{port}/"
    sisa = bersihkan_sisa_lama()
    srv = ThreadingHTTPServer(("127.0.0.1", port), Penangan)
    srv.daemon_threads = True
    print("=" * 70)
    print("  APLIKASI OTOMASI GAJI DPP sudah jalan.")
    print(f"  Buka di peramban:  {alamat}")
    print("  Semuanya berjalan di komputer ini saja; tidak ada data yang dikirim ke internet.")
    print(f"  Database personel: {personel_db.lokasi()}")
    print("  Kalau sudah selesai, tekan Ctrl+C di jendela ini: salinan sementara langsung dihapus.")
    print("  (Menutup jendela lewat tombol X meninggalkan salinan itu sampai aplikasi dinyalakan lagi.)")
    if sisa:
        print(f"  {sisa} folder sementara dari pemakaian sebelumnya sudah dibersihkan.")
    print("=" * 70, flush=True)
    if buka:
        threading.Timer(0.6, lambda: webbrowser.open(alamat)).start()
    try:
        srv.serve_forever()
    except KeyboardInterrupt:
        print("\nMenutup aplikasi ...")
    finally:
        srv.shutdown()
        bersihkan_semua()
        print("Folder sementara sudah dibersihkan.")


if __name__ == "__main__":
    sys.exit(main())
