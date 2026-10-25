"""
Memperbarui workbook pembukuan gaji dari data DPP.

Workbook disunting langsung di level XML: sel GAJI di sheet BUKU TNI diisi dari DPP, kolom
potongan pada baris anggota dikosongkan (diisi ulang oleh juru bayar dan pihak bank), dan label
bulan diganti. Formula, grafik, tautan eksternal, format cetak, dan sheet lain (STRUK, REKAP,
TUNGGAKAN, dll.) tidak disentuh, lalu Excel diminta menghitung ulang saat file dibuka.
"""
import json
import re
import shutil
import subprocess
import zipfile
from difflib import SequenceMatcher
from pathlib import Path

import openpyxl
from lxml import etree

DASAR = Path(__file__).resolve().parent
TEMPLATE_BAKU = DASAR / "template_pembukuan.json"

# ---- Tata letak pembukuan dibaca dari template_pembukuan.json. Nilai di bawah hanya
#      cadangan bila file template itu tidak ada. ----
TEMPLATE_BAWAAN = {
    "sheet": "BUKU TNI",
    "header": {"kolom": "E", "teks": "NRP/NIP", "cari_baris": [1, 19]},
    "akhir_tabel": {"kolom": "E", "teks": "JUMLAH"},
    "kolom": {"no": "A", "nama": "B", "pangkat": "C", "nrp": "E", "gaji": "F", "rekening": "AS"},
    "label_bulan": {"teks": "BULAN", "nilai_di": "kanan"},
    "seksi_keluar": {"kolom": "B", "mulai": "KELUAR", "henti": "MASUK"},
    "baris_data": {"min_digit_nrp": 5, "kolom_no_harus_angka": True},
    "kosongkan": {"dari": "G", "sampai": "AI"},
}
AMBANG_NAMA = 0.86          # kemiripan nama minimum untuk pencocokan cadangan
AMBANG_KONFLIK = 0.50       # NRP sama tapi nama di bawah ini = NRP milik orang lain

RE_WORKSHEET = re.compile(r"^xl/worksheets/[^/]+\.xml$")

NS_MAIN = "http://schemas.openxmlformats.org/spreadsheetml/2006/main"
NS_REL = "http://schemas.openxmlformats.org/officeDocument/2006/relationships"
NS = {"m": NS_MAIN}


# ----------------------------------------------------------------------------- utilitas
def _kotak(judul: str, *bagian) -> str:
    """Pesan kesalahan berkerangka, agar menonjol di jendela .bat yang penuh teks."""
    garis = "=" * 74
    isi = [garis, judul.upper(), garis]
    for b in bagian:
        if not b:
            continue
        isi.append("")
        isi.extend(str(x) for x in (b if isinstance(b, (list, tuple)) else [b]))
    return "\n" + "\n".join(isi + ["", garis])


def _sama(nilai, teks) -> bool:
    """Bandingkan isi sel dengan penanda template; huruf besar/kecil dan spasi diabaikan."""
    a, b = (re.sub(r"\s+", "", str(x or "")).upper() for x in (nilai, teks))
    return bool(b) and a == b


def _potong(nilai, n=28) -> str:
    s = "" if nilai is None else str(nilai).replace("\n", " ").strip()
    return (s[: n - 1] + "..") if len(s) > n else (s or "(kosong)")


def digit(s) -> str:
    return re.sub(r"\D", "", str(s or ""))


GELAR_DEPAN = {"DRS", "DRA", "DR", "PDT", "IR", "H", "HJ", "PROF"}


def nama_inti(s: str) -> str:
    """Buang gelar, keterangan dalam kurung, dan tanda baca agar nama bisa dibandingkan."""
    s = str(s or "").upper()
    s = re.sub(r"\(.*?\)", " ", s).split(",")[0]
    tok = re.sub(r"[^A-Z ]", " ", s).split()
    while tok and tok[0] in GELAR_DEPAN:
        tok = tok[1:]
    return " ".join(tok)


def mirip(a, b) -> float:
    """Kemiripan 0-1, tahan singkatan (POPPY ANIS P. = POPPY ANIS PUSPARINI)."""
    x, y = nama_inti(a), nama_inti(b)
    if not x or not y:
        return 0.0
    seq = SequenceMatcher(None, x, y).ratio()
    tx, ty = x.split(), y.split()
    pendek, panjang = (tx, ty) if len(tx) <= len(ty) else (ty, tx)
    cocok = sum(1 for p, q in zip(pendek, panjang) if q.startswith(p) or p.startswith(q))
    return max(seq, cocok / len(panjang) if len(pendek) == len(panjang) else cocok / len(pendek) * 0.95)


def pastikan_xlsx(path: Path) -> Path:
    """File .xls dikonversi ke .xlsx memakai LibreOffice bila tersedia."""
    if path.suffix.lower() == ".xlsx":
        return path
    calon = [shutil.which("soffice"), shutil.which("libreoffice"),
             r"C:\Program Files\LibreOffice\program\soffice.exe",
             r"C:\Program Files (x86)\LibreOffice\program\soffice.exe"]
    soffice = next((c for c in calon if c and Path(c).exists()), None)
    if not soffice:
        raise SystemExit(
            f"'{path.name}' masih format .xls lama. Buka di Excel lalu Simpan Sebagai "
            "'Excel Workbook (*.xlsx)', kemudian jalankan ulang. (Cukup sekali.)")
    subprocess.run([soffice, "--headless", "--convert-to", "xlsx", "--outdir",
                    str(path.parent), str(path)], check=True, capture_output=True, timeout=180)
    hasil = path.with_suffix(".xlsx")
    if not hasil.exists():
        raise SystemExit("Konversi .xls ke .xlsx gagal.")
    return hasil


# ------------------------------------------------------------------- template tata letak
def muat_template(path=None) -> dict:
    """Baca template_pembukuan.json, lengkapi dengan nilai bawaan, lalu periksa isinya."""
    path = Path(path) if path else TEMPLATE_BAKU
    tpl = {k: (dict(v) if isinstance(v, dict) else v) for k, v in TEMPLATE_BAWAAN.items()}
    catatan = []
    if path.exists():
        try:
            isi = json.loads(path.read_text(encoding="utf-8"))
        except json.JSONDecodeError as e:
            raise SystemExit(_kotak(
                "template tata letak rusak",
                [f"File  : {path}",
                 f"Galat : {e.msg} (baris {e.lineno}, kolom {e.colno})"],
                ["Penyebab tersering: tanda kutip atau koma yang kurang/berlebih.",
                 "Perbaiki file itu, atau hapus saja - program akan memakai tata letak bawaan."]))
        if not isinstance(isi, dict):
            raise SystemExit(_kotak(
                "template tata letak rusak",
                f"File {path} harus berisi satu objek JSON tunggal."))
        asing = [k for k in isi if not k.startswith("_") and k not in TEMPLATE_BAWAAN]
        if asing:
            catatan.append("kunci tidak dikenal, diabaikan: " + ", ".join(asing))
        for k, v in isi.items():
            if k.startswith("_") or k not in tpl:
                continue
            if isinstance(tpl[k], dict) and isinstance(v, dict):
                tpl[k].update({a: b for a, b in v.items() if not a.startswith("_")})
            else:
                tpl[k] = v
        tpl["_sumber"] = path.name
    else:
        catatan.append(f"{path.name} tidak ada, memakai tata letak bawaan")
        tpl["_sumber"] = "tata letak bawaan di pembukuan.py"
    _periksa_template(tpl, path)
    tpl["_catatan"] = catatan
    return tpl


def _periksa_template(tpl: dict, path: Path):
    """Tolak template yang tidak masuk akal sebelum file pembukuan sempat dibuka."""
    def salah(*bagian):
        raise SystemExit(_kotak("isi template tata letak tidak sah", f"File: {path}", *bagian))

    def kolom(nilai, dimana, boleh_kosong=False):
        if nilai in (None, "") and boleh_kosong:
            return None
        if not isinstance(nilai, str) or not re.fullmatch(r"[A-Za-z]{1,3}", nilai.strip()):
            salah(f"{dimana} = {nilai!r} bukan huruf kolom Excel yang sah.",
                  'Tulis seperti di Excel, contoh: "A", "F", "AS".')
        try:
            openpyxl.utils.column_index_from_string(nilai.strip().upper())
        except ValueError:
            salah(f"{dimana} = {nilai!r} di luar jangkauan kolom Excel.")
        return nilai.strip().upper()

    def teks(bagian, kunci):
        if not str(tpl[bagian].get(kunci) or "").strip():
            salah(f'"{bagian}" -> "{kunci}" tidak boleh kosong.')

    if not isinstance(tpl["sheet"], str) or not tpl["sheet"].strip():
        salah('"sheet" harus berisi nama sheet pembukuan, contoh "BUKU TNI".')
    tpl["sheet"] = tpl["sheet"].strip()

    for bagian in ("header", "akhir_tabel"):
        tpl[bagian]["kolom"] = kolom(tpl[bagian].get("kolom"), f'"{bagian}" -> "kolom"')
        teks(bagian, "teks")

    cb = tpl["header"].get("cari_baris")
    if (not isinstance(cb, (list, tuple)) or len(cb) != 2
            or not all(isinstance(x, int) and not isinstance(x, bool) for x in cb)
            or not 1 <= cb[0] <= cb[1]):
        salah('"header" -> "cari_baris" harus [awal, akhir], contoh [1, 19],',
              "dengan awal minimal 1 dan tidak lebih besar dari akhir.",
              f"Sekarang: {cb!r}")
    tpl["header"]["cari_baris"] = [int(cb[0]), int(cb[1])]

    for peran in ("no", "nama", "pangkat", "nrp", "gaji", "rekening"):
        tpl["kolom"][peran] = kolom(tpl["kolom"].get(peran), f'"kolom" -> "{peran}"')
    # Kolom pilihan, hanya dipakai saat template kosong diisi dari DPP.
    for peran in ("korps", "nama_rekening", "no_record"):
        tpl["kolom"][peran] = kolom(tpl["kolom"].get(peran), f'"kolom" -> "{peran}"',
                                    boleh_kosong=True)

    if tpl["label_bulan"].get("nilai_di", "kanan") not in ("kanan", "bawah"):
        salah('"label_bulan" -> "nilai_di" hanya boleh "kanan" atau "bawah".',
              f'Sekarang: {tpl["label_bulan"].get("nilai_di")!r}')

    tpl["seksi_keluar"]["kolom"] = kolom(tpl["seksi_keluar"].get("kolom"),
                                         '"seksi_keluar" -> "kolom"', boleh_kosong=True)
    if tpl["seksi_keluar"]["kolom"]:
        teks("seksi_keluar", "mulai")
        teks("seksi_keluar", "henti")

    bd = tpl["baris_data"]
    if (isinstance(bd.get("min_digit_nrp"), bool)
            or not isinstance(bd.get("min_digit_nrp"), int) or bd["min_digit_nrp"] < 0):
        salah('"baris_data" -> "min_digit_nrp" harus bilangan bulat >= 0, contoh 5.',
              f'Sekarang: {bd.get("min_digit_nrp")!r}')
    bd["kolom_no_harus_angka"] = bool(bd.get("kolom_no_harus_angka", True))

    ks = tpl["kosongkan"]
    ks["dari"] = kolom(ks.get("dari"), '"kosongkan" -> "dari"', boleh_kosong=True)
    ks["sampai"] = kolom(ks.get("sampai"), '"kosongkan" -> "sampai"', boleh_kosong=True)
    if bool(ks["dari"]) != bool(ks["sampai"]):
        salah('"kosongkan" -> "dari" dan "sampai" harus diisi dua-duanya,',
              "atau dua-duanya null bila kolom potongan tidak ingin dikosongkan.")
    if ks["dari"]:
        awal, akhir = (_kol_ke_angka(ks[x]) for x in ("dari", "sampai"))
        if awal > akhir:
            salah(f'"kosongkan": kolom "dari" ({ks["dari"]}) berada di kanan "sampai" ({ks["sampai"]}).')
        kena = [f'{peran}={tpl["kolom"][peran]}' for peran in tpl["kolom"]
                if not peran.startswith("_") and tpl["kolom"][peran]
                and awal <= _kol_ke_angka(tpl["kolom"][peran]) <= akhir]
        if kena:
            salah(f'"kosongkan" {ks["dari"]}..{ks["sampai"]} mencakup kolom data anggota: '
                  + ", ".join(kena) + ".",
                  "Rentang ini hanya boleh berisi kolom potongan.")


# ----------------------------------------------------------------------------- baca buku
def baca_buku(path: Path, tpl: dict = None, boleh_kosong=False):
    """Baca sheet pembukuan sesuai template. Penanda yang tidak ketemu selalu dijelaskan.

    boleh_kosong: buku tanpa satu pun anggota (template kosong) tidak ditolak; pemanggil
    mengisinya dari DPP mulai `baris_pertama`.
    """
    tpl = tpl or muat_template()
    k, nama_file = tpl["kolom"], Path(path).name
    try:
        wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    except Exception as e:
        raise SystemExit(_kotak(
            "file pembukuan tidak bisa dibuka",
            [f"File  : {path}", f"Galat : {type(e).__name__}: {e}"],
            ["Pastikan file itu benar-benar .xlsx (bukan .xls yang diganti namanya),",
             "tidak rusak, dan tidak sedang terbuka di Excel."]))
    try:
        if tpl["sheet"] not in wb.sheetnames:
            raise SystemExit(_kotak(
                "sheet pembukuan tidak ditemukan",
                [f'Dicari sheet : "{tpl["sheet"]}"', f"Di file      : {nama_file}"],
                "Sheet yang ada di file ini:",
                ["  - " + s for s in wb.sheetnames],
                [f'Perbaiki: buka {tpl["_sumber"]}, ubah baris',
                 f'    "sheet": "{tpl["sheet"]}"',
                 "menjadi salah satu nama sheet di atas, lalu jalankan ulang."]))
        ws = wb[tpl["sheet"]]
        rows = list(ws.iter_rows(values_only=False))

        def val(r, kol):
            if not 1 <= r <= len(rows):
                return None
            idx = openpyxl.utils.column_index_from_string(kol) - 1
            baris = rows[r - 1]
            return baris[idx].value if idx < len(baris) else None

        # --- baris header ---
        h = tpl["header"]
        r_awal, r_batas = h["cari_baris"]
        r_akhir = min(r_batas, len(rows))
        header = next((r for r in range(r_awal, r_akhir + 1)
                       if _sama(val(r, h["kolom"]), h["teks"])), None)
        if header is None:
            raise SystemExit(_kotak(
                "baris header pembukuan tidak ditemukan",
                [f'Dicari tulisan : "{h["teks"]}"',
                 f'Di kolom       : {h["kolom"]}',
                 f"Baris          : {r_awal} sampai {r_batas}"
                 + ("" if r_akhir == r_batas else f" (sheet ini hanya punya {len(rows)} baris)"),
                 f'Sheet          : {tpl["sheet"]} ({nama_file})'],
                f'Isi kolom {h["kolom"]} pada baris yang diperiksa:',
                [f"  baris {r:>3} : {_potong(val(r, h['kolom']))}"
                 for r in range(r_awal, r_akhir + 1)] or ["  (sheet kosong)"],
                [f'Perbaiki di {tpl["_sumber"]}, bagian "header":',
                 '  "kolom"      -> huruf kolom tempat judul NRP/NIP berada',
                 '  "teks"       -> tulisan persis seperti di pembukuan',
                 '  "cari_baris" -> perluas bila header berada lebih ke bawah']))

        # --- baris penutup tabel ---
        a = tpl["akhir_tabel"]
        akhir = next((r for r in range(header + 1, len(rows) + 1)
                      if _sama(val(r, a["kolom"]), a["teks"])), None)
        if akhir is None:
            terisi = [r for r in range(header + 1, len(rows) + 1)
                      if str(val(r, a["kolom"]) or "").strip()]
            raise SystemExit(_kotak(
                "baris penutup tabel tidak ditemukan",
                [f'Dicari tulisan : "{a["teks"]}"',
                 f'Di kolom       : {a["kolom"]}',
                 f"Baris          : {header + 1} sampai {len(rows)}"
                 f" (di bawah header yang terbaca di baris {header})",
                 f'Sheet          : {tpl["sheet"]} ({nama_file})'],
                f'Isi kolom {a["kolom"]} yang tidak kosong di bawah header'
                + (" (15 terakhir)" if len(terisi) > 15 else "") + ":",
                [f"  baris {r:>3} : {_potong(val(r, a['kolom']))}"
                 for r in terisi[-15:]] or [f'  (kolom {a["kolom"]} kosong seluruhnya)'],
                [f'Perbaiki di {tpl["_sumber"]}, bagian "akhir_tabel":',
                 '  "kolom" dan "teks" harus menunjuk baris subtotal penutup tabel',
                 '  (di pembukuan contoh: tulisan "JUMLAH" di kolom E).']))

        # --- label bulan di atas header ---
        lb = tpl["label_bulan"]
        bulan_lama, sel_bulan = None, None
        penanda_bulan = str(lb.get("teks") or "").strip()
        if penanda_bulan:
            for r in range(1, header):
                for c in rows[r - 1]:
                    if not _sama(c.value, penanda_bulan):
                        continue
                    if lb.get("nilai_di", "kanan") == "bawah":
                        sasaran = (rows[r][c.column - 1]
                                   if r < len(rows) and c.column - 1 < len(rows[r]) else None)
                    else:
                        sasaran = rows[r - 1][c.column] if c.column < len(rows[r - 1]) else None
                    if sasaran is not None and str(sasaran.value or "").strip():
                        bulan_lama, sel_bulan = str(sasaran.value).strip(), sasaran.coordinate

        # --- baris data anggota ---
        bd = tpl["baris_data"]
        data = []
        for r in range(header + 1, akhir):
            nrp = digit(val(r, k["nrp"]))
            no = val(r, k["no"])
            if len(nrp) < bd["min_digit_nrp"]:
                continue
            if bd["kolom_no_harus_angka"] and not isinstance(no, (int, float)):
                continue
            data.append({"baris": r, "no": int(no) if isinstance(no, (int, float)) else 0,
                         "nama": str(val(r, k["nama"]) or "").strip(),
                         "pangkat": str(val(r, k["pangkat"]) or "").strip(), "nrp": nrp,
                         "nrp_asli": str(val(r, k["nrp"])), "gaji_lama": val(r, k["gaji"]) or 0,
                         "rekening": digit(val(r, k["rekening"])),
                         # dipakai impor bank & rekening ke database personel (personel_db.py)
                         "nama_rekening": (str(val(r, k["nama_rekening"]) or "").strip()
                                           if k.get("nama_rekening") else "")})

        # Baris pertama tabel: lewati sambungan header ("(RP)") dan baris nomor kolom (1, 2, 3, ..).
        baris_pertama = header + 1
        while baris_pertama < akhir and (
                isinstance(val(baris_pertama, k["nama"]), (int, float))
                or (isinstance(val(baris_pertama, k["gaji"]), str)
                    and val(baris_pertama, k["gaji"]).strip())):
            baris_pertama += 1
        if data:
            baris_pertama = min(baris_pertama, data[0]["baris"])

        if not data and not boleh_kosong:
            syarat = [f'  - kolom {k["nrp"]} (nrp) memuat minimal {bd["min_digit_nrp"]} angka']
            if bd["kolom_no_harus_angka"]:
                syarat.append(f'  - kolom {k["no"]} (no) berisi angka, bukan teks')
            raise SystemExit(_kotak(
                "tidak ada satu pun baris anggota yang terbaca",
                [f"Header terbaca di baris {header}, penutup tabel di baris {akhir}, jadi program",
                 f"memeriksa baris {header + 1}-{akhir - 1} tetapi semuanya tidak lolos syarat.",
                 f'Sheet: {tpl["sheet"]} ({nama_file})'],
                "Syarat sebuah baris dianggap data anggota:", syarat,
                "Isi sebenarnya (maksimal 10 baris pertama):",
                [f'  baris {r:>3} : {k["no"]}={_potong(val(r, k["no"]), 12)}'
                 f' | {k["nrp"]}={_potong(val(r, k["nrp"]), 20)}'
                 f' | {k["nama"]}={_potong(val(r, k["nama"]), 22)}'
                 for r in range(header + 1, min(akhir, header + 11))],
                ["Dua kemungkinan:",
                 "  1. Pembukuan ini memang belum diisi anggotanya. Isi dulu kolom nama,",
                 "     pangkat, NRP/NIP, dan nomor rekening, lalu jalankan lagi.",
                 f'  2. Peta kolom di {tpl["_sumber"]} tidak cocok dengan pembukuan ini.',
                 "     Yang paling sering salah: kolom nrp dan kolom no."]))

        # Seksi KELUAR di bawah tabel (pindah/pensiun) untuk deteksi pembayaran yang mestinya berhenti
        keluar, mode = [], False
        sk = tpl["seksi_keluar"]
        if sk["kolom"]:
            for r in range(akhir + 1, len(rows) + 1):
                b = str(val(r, sk["kolom"]) or "").strip()
                if _sama(b, sk["mulai"]):
                    mode = True
                    continue
                if _sama(b, sk["henti"]):
                    break
                if mode and b:
                    keluar.append({"baris": r, "nama": b,
                                   "pangkat": str(val(r, k["pangkat"]) or ""),
                                   "ket": str(val(r, k["nrp"]) or "")})
    finally:
        wb.close()
    return {"anggota": data, "bulan": bulan_lama, "sel_bulan": sel_bulan,
            "baris_jumlah": akhir, "keluar": keluar, "header": header,
            "baris_pertama": baris_pertama}


# ------------------------------------------------------------- isi template kosong dari DPP
URUT_PANGKAT = ["JENDERAL", "LETJEN", "MAYJEN", "BRIGJEN", "KOLONEL", "LETKOL", "MAYOR", "KAPTEN",
                "LETTU", "LETDA", "PELTU", "PELDA", "SERMA", "SERKA", "SERTU", "SERDA",
                "KOPKA", "KOPTU", "KOPDA", "PRAKA", "PRATU", "PRADA"]
PERWIRA_BERKORPS = {"KOLONEL", "LETKOL", "MAYOR", "KAPTEN", "LETTU", "LETDA"}


def pangkat_buku(pangkat: str, korps: str) -> str:
    """Tulis pangkat seperti di buku: perwira diikuti korps (KOLONEL CAJ), lainnya tidak."""
    p, kp = str(pangkat or "").strip(), str(korps or "").strip().upper()
    if p.split()[:1] and p.split()[0].upper() in PERWIRA_BERKORPS and kp and kp != "TNI" \
            and kp not in p.upper().split():
        return f"{p} {kp}"
    return p


def susun_dari_dpp(dpp_list, buku: dict, tpl: dict):
    """Buku kosong: susun isi tabel dari DPP, urut pangkat tertinggi dulu.

    Mengembalikan (update {baris: gaji}, teks {sel: str}, angka {sel: int}, temuan, pasangan).
    """
    k = tpl["kolom"]
    awal, akhir = buku["baris_pertama"], buku["baris_jumlah"] - 1
    if len(dpp_list) > akhir - awal + 1:
        raise SystemExit(_kotak(
            "baris di template kurang",
            [f"DPP memuat {len(dpp_list)} anggota, tetapi tabel di sheet {tpl['sheet']} hanya",
             f"punya {akhir - awal + 1} baris (baris {awal} sampai {akhir})."],
            ["Tambah baris di template (sisipkan di atas baris JUMLAH supaya rumusnya ikut),",
             "lalu jalankan ulang."]))

    def urut(d):
        kata = (d["pangkat"] or "").split()
        return URUT_PANGKAT.index(kata[0].upper()) if kata and kata[0].upper() in URUT_PANGKAT \
            else len(URUT_PANGKAT)

    update, teks, angka, temuan, pasangan = {}, {}, {}, [], []
    hitung_nrp = {}
    for d in dpp_list:
        hitung_nrp.setdefault(digit(d["nrp"]), []).append(d)
    for i, d in enumerate(sorted(dpp_list, key=urut), start=1):   # sorted() stabil: urutan PDF terjaga
        r = awal + i - 1
        b = {"baris": r, "no": i, "nama": d["nama"], "pangkat": pangkat_buku(d["pangkat"], d["korps"]),
             "nrp": digit(d["nrp"]), "nrp_asli": d["nrp"], "gaji_lama": 0,
             "rekening": digit(d["rekening"])}
        update[r] = d["dibayarkan"]
        angka[f'{k["no"]}{r}'] = i
        teks[f'{k["nama"]}{r}'] = d["nama"]
        teks[f'{k["pangkat"]}{r}'] = b["pangkat"]
        teks[f'{k["nrp"]}{r}'] = d["nrp"]
        teks[f'{k["rekening"]}{r}'] = d["rekening"]
        if k.get("korps"):
            teks[f'{k["korps"]}{r}'] = d["korps"]
        if k.get("nama_rekening"):
            teks[f'{k["nama_rekening"]}{r}'] = d["nama"]
        if k.get("no_record"):
            angka[f'{k["no_record"]}{r}'] = i
        pasangan.append((b, d, "PDF"))

        def t(tingkat, kategori, detail):
            temuan.append({"tingkat": tingkat, "kategori": kategori, "baris": r,
                           "nrp": d["nrp"], "nama": d["nama"], "detail": detail})
        ganda = hitung_nrp[b["nrp"]]
        if not b["nrp"]:
            t("TINGGI", "NRP tidak terbaca dari hasil scan", "Kolom NRP dibiarkan kosong; isi manual "
              "dari kertas aslinya.")
        elif len(ganda) > 1:
            t("TINGGI", "NRP ganda di DPP", f"NRP muncul {len(ganda)}x di: "
              + ", ".join(f"{x['file']} no.{x['no_urut']}" for x in ganda)
              + ". Semuanya ditulis ke buku; hapus baris yang salah.")
        if d["masalah"]:
            t("TINGGI", "Hitungan DPP tidak konsisten", "; ".join(d["masalah"]))
        if not d["pangkat"]:
            t("SEDANG", "Pangkat tidak terbaca dari PDF", "Kolom pangkat dibiarkan kosong; isi manual.")
        if not d["rekening"]:
            t("SEDANG", "Nomor rekening tidak ada di PDF", "Kolom rekening dibiarkan kosong; isi manual.")
    return update, teks, angka, temuan, pasangan


# ----------------------------------------------------------------------------- pencocokan
def cocokkan(buku, dpp_list, keluar=()):
    """Mengembalikan (update {baris: gaji_baru}, temuan [..], pasangan [..], dpp_tanpa_buku [..])."""
    per_nrp = {}
    for d in dpp_list:
        per_nrp.setdefault(digit(d["nrp"]), []).append(d)
    temuan, update, pasangan, terpakai = [], {}, [], set()

    def t(tingkat, kategori, b, d, detail):
        temuan.append({"tingkat": tingkat, "kategori": kategori,
                       "baris": b["baris"] if b else None,
                       "nrp": (b or d)["nrp"], "nama": (b or d)["nama"], "detail": detail})

    for nrp, ds in per_nrp.items():
        if len(ds) > 1 and nrp:            # NRP kosong = tidak terbaca dari hasil scan, bukan ganda
            t("TINGGI", "NRP ganda di DPP", None, ds[0],
              f"NRP muncul {len(ds)}x di: " + ", ".join(f"{x['file']} no.{x['no_urut']}" for x in ds))

    def scan(d):
        """Tambahan keterangan bila data DPP-nya dibaca dari hasil scan, bukan dari lapisan teks."""
        return (" Catatan: data DPP ini dibaca dari hasil scan (OCR), jadi perbedaannya bisa juga "
                "karena salah baca. Cocokkan dengan kertas aslinya.") if d.get("ocr") else ""

    per_nrp_buku = {}
    for b in buku:
        per_nrp_buku.setdefault(b["nrp"], []).append(b)
    for nrp, bs in per_nrp_buku.items():
        if len(bs) > 1:
            for b in bs:
                t("TINGGI", "NRP ganda di buku", b, None,
                  f"NRP {b['nrp_asli']} dipakai di baris " + ", ".join(str(x["baris"]) for x in bs) +
                  " (" + " / ".join(x["nama"] for x in bs) + ").")

    # Tahap 1: cocok NRP persis
    belum = []
    for b in buku:
        ds = per_nrp.get(b["nrp"]) if b["nrp"] else None
        if ds and mirip(b["nama"], ds[0]["nama"]) < AMBANG_KONFLIK:
            d = ds[0]
            t("TINGGI", "NRP di buku milik orang lain", b, d,
              f"NRP {b['nrp_asli']} di buku tertulis a.n. {b['nama']}, tetapi di DPP NRP ini milik "
              f"{d['nama']} ({d['pangkat']}). Tidak dipakai; dicari ulang berdasarkan nama.")
            belum.append(b)
        elif ds:
            d = ds[0]
            terpakai.add(id(d))
            pasangan.append((b, d, "NRP"))
        else:
            belum.append(b)

    # Tahap 2: cadangan berdasarkan nama untuk NRP yang salah ketik
    sisa_dpp = [d for d in dpp_list if id(d) not in terpakai]
    for b in belum:
        def pendukung(d):
            """Nama mirip saja tidak cukup: harus didukung NRP yang mirip ATAU pangkat yang sama."""
            nrp_mirip = SequenceMatcher(None, b["nrp"], digit(d["nrp"])).ratio() >= 0.75
            pk = d["pangkat"].split()[0] if d["pangkat"] else "?"
            return nrp_mirip or b["pangkat"].upper().startswith(pk)

        kandidat = sorted(((mirip(b["nama"], d["nama"]), d) for d in sisa_dpp if pendukung(d)),
                          key=lambda x: -x[0])
        if kandidat and kandidat[0][0] >= AMBANG_NAMA:
            skor, d = kandidat[0]
            sisa_dpp.remove(d)
            terpakai.add(id(d))
            pasangan.append((b, d, "NAMA"))
            t("TINGGI", "NRP di buku berbeda dengan DPP", b, d,
              f"Buku: {b['nrp_asli']}  |  DPP: {d['nrp']} ({d['file']} no.{d['no_urut']}). "
              f"Cocok via nama ({skor:.0%}). Gaji tetap diperbarui, mohon koreksi NRP di buku."
              + scan(d))
        else:
            t("TINGGI", "Tidak ditemukan di DPP", b, None,
              f"Gaji TIDAK diperbarui, masih nilai lama Rp {b['gaji_lama']:,.0f}. "
              "Cek: pindah/pensiun, atau PDF golongannya belum dimasukkan.")

    for b, d, cara in pasangan:
        update[b["baris"]] = d["dibayarkan"]
        if round(b["gaji_lama"]) != d["dibayarkan"]:
            t("INFO", "Gaji berubah", b, d,
              f"Lama Rp {b['gaji_lama']:,.0f} -> baru Rp {d['dibayarkan']:,.0f} "
              f"(selisih {d['dibayarkan'] - b['gaji_lama']:+,.0f})")
        if b["rekening"] and d["rekening"] and b["rekening"] != digit(d["rekening"]):
            t("TINGGI", "Nomor rekening berbeda", b, d,
              f"Buku: {b['rekening']}  |  DPP: {d['rekening']}. Tidak diubah otomatis; "
              "verifikasi ke ybs sebelum transfer." + scan(d))
        pk_dpp = d["pangkat"].split()[0] if d["pangkat"] else ""
        if pk_dpp and not b["pangkat"].upper().startswith(pk_dpp):
            t("SEDANG", "Pangkat berbeda", b, d, f"Buku: {b['pangkat']}  |  DPP: {d['pangkat']}" + scan(d))
        if cara == "NRP" and mirip(b["nama"], d["nama"]) < 0.80:
            t("SEDANG", "Nama berbeda untuk NRP sama", b, d,
              f"Buku: {b['nama']}  |  DPP: {d['nama']}" + scan(d))
        if d["masalah"]:
            t("TINGGI", "Hitungan DPP tidak konsisten", b, d, "; ".join(d["masalah"]))

    dpp_tanpa_buku = [d for d in dpp_list if id(d) not in terpakai]
    for d in list(dpp_tanpa_buku):
        k = next((k for k in keluar if mirip(k["nama"], d["nama"]) >= AMBANG_NAMA), None)
        if k:
            t("TINGGI", "Tercatat KELUAR tapi masih dibayar di DPP", None, d,
              f"Di buku baris {k['baris']}: {k['nama']} - {k['ket']}. Masih tercantum di DPP "
              f"({d['file']} no.{d['no_urut']}) dibayarkan Rp {d['dibayarkan']:,}. "
              "Konfirmasi ke bagian personel/keuangan (potensi kelebihan bayar).")
            continue
        t("TINGGI", "Ada di DPP, belum ada di buku", None, d,
          f"{d['pangkat']} {d['korps']}, dibayarkan Rp {d['dibayarkan']:,} ({d['file']} no.{d['no_urut']}). "
          "Tambahkan ke BUKU TNI beserta potongannya.")
    return update, temuan, pasangan, dpp_tanpa_buku


# ----------------------------------------------------------------------------- tulis XML
def _path_sheet(z: zipfile.ZipFile, nama: str) -> str:
    wbx = etree.fromstring(z.read("xl/workbook.xml"))
    sh = next((s for s in wbx.find("m:sheets", NS) if s.get("name") == nama), None)
    if sh is None:
        raise SystemExit(_kotak(
            "sheet pembukuan tidak ditemukan di dalam file xlsx",
            f'Sheet "{nama}" tidak terdaftar di file sumber.',
            ["Buka file itu di Excel, Simpan Sebagai .xlsx sekali lagi, lalu jalankan ulang."]))
    rid = sh.get(f"{{{NS_REL}}}id")
    rels = etree.fromstring(z.read("xl/_rels/workbook.xml.rels"))
    target = next(r.get("Target") for r in rels if r.get("Id") == rid)
    return "xl/" + target.lstrip("/").removeprefix("xl/")


def _kol_ke_angka(kol):
    return openpyxl.utils.column_index_from_string(kol)


def _gaya_kolom(sd) -> dict:
    """Gaya yang paling lazim dipakai tiap kolom, untuk sel yang belum ada di XML.

    Sel yang sama sekali belum ada tidak punya atribut `s`, jadi kalau dibuat begitu saja
    ia memakai gaya bawaan dan format rupiahnya hilang - kolom GAJI jadi tampil sebagai
    angka mentah. Gaya tetangganya di kolom yang sama dipakai supaya keluarannya tetap rapi.
    """
    hitung = {}
    for row in sd.findall("m:row", NS):
        for c in row.findall("m:c", NS):
            gaya = c.get("s")
            if gaya is None:
                continue
            kol = re.match(r"[A-Z]+", c.get("r") or "")
            if kol:
                per_kol = hitung.setdefault(kol.group(), {})
                per_kol[gaya] = per_kol.get(gaya, 0) + 1
    return {k: max(v, key=v.get) for k, v in hitung.items()}


def _set_angka(sheet_xml: bytes, nilai: dict) -> tuple[bytes, list]:
    root = etree.fromstring(sheet_xml)
    sd = root.find("m:sheetData", NS)
    rows = {int(r.get("r")): r for r in sd.findall("m:row", NS)}
    gaya_kolom = _gaya_kolom(sd)
    dilewati = []
    for ref, v in nilai.items():
        kol, no = re.match(r"([A-Z]+)(\d+)", ref).groups()
        row = rows.get(int(no))
        if row is None:
            raise SystemExit(_kotak(
                "baris pembukuan tidak ada di dalam file xlsx",
                [f"Sel {ref} seharusnya diisi, tetapi baris {no} sama sekali tidak",
                 "punya isi di file sumber, sehingga tidak bisa disunting."],
                ["Buka file sumber di Excel, simpan ulang sebagai .xlsx, lalu jalankan ulang."]))
        c = row.find(f"m:c[@r='{ref}']", NS)
        if c is None:
            c = etree.Element(f"{{{NS_MAIN}}}c", r=ref)
            if kol in gaya_kolom:
                c.set("s", gaya_kolom[kol])
            setelah = [x for x in row.findall("m:c", NS)
                       if _kol_ke_angka(re.match(r"[A-Z]+", x.get("r")).group()) < _kol_ke_angka(kol)]
            (setelah[-1].addnext(c) if setelah else row.insert(0, c))
        if c.find("m:f", NS) is not None:
            dilewati.append(ref)            # jangan pernah menimpa formula
            continue
        for anak in list(c):
            c.remove(anak)
        c.set("t", "n")
        etree.SubElement(c, f"{{{NS_MAIN}}}v").text = str(int(v))
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True), dilewati


def _sel(row, ref: str, gaya_kolom: dict):
    """Ambil sel `ref` di baris itu; buat (dengan gaya kolomnya) bila belum ada."""
    c = row.find(f"m:c[@r='{ref}']", NS)
    if c is None:
        kol = re.match(r"[A-Z]+", ref).group()
        c = etree.Element(f"{{{NS_MAIN}}}c", r=ref)
        if kol in gaya_kolom:
            c.set("s", gaya_kolom[kol])
        kiri = [x for x in row.findall("m:c", NS)
                if _kol_ke_angka(re.match(r"[A-Z]+", x.get("r")).group()) < _kol_ke_angka(kol)]
        (kiri[-1].addnext(c) if kiri else row.insert(0, c))
    return c


def _set_teks(sheet_xml: bytes, nilai: dict) -> tuple[bytes, list]:
    """Tulis teks sebagai inline string. Sel berformula dilewati; teks kosong tidak ditulis."""
    root = etree.fromstring(sheet_xml)
    sd = root.find("m:sheetData", NS)
    rows = {int(r.get("r")): r for r in sd.findall("m:row", NS)}
    gaya_kolom = _gaya_kolom(sd)
    dilewati = []
    for ref, v in nilai.items():
        if not str(v or "").strip():
            continue
        row = rows.get(int(re.search(r"\d+", ref).group()))
        if row is None:
            raise SystemExit(_kotak(
                "baris template tidak ada di dalam file xlsx",
                [f"Sel {ref} seharusnya diisi, tetapi barisnya tidak ada di file sumber."],
                ["Buka template di Excel/LibreOffice, simpan ulang sebagai .xlsx, lalu jalankan ulang."]))
        c = _sel(row, ref, gaya_kolom)
        if c.find("m:f", NS) is not None:
            dilewati.append(ref)
            continue
        for anak in list(c):
            c.remove(anak)
        c.set("t", "inlineStr")
        t = etree.SubElement(etree.SubElement(c, f"{{{NS_MAIN}}}is"), f"{{{NS_MAIN}}}t")
        t.text = str(v)
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True), dilewati


def _kosongkan(sheet_xml: bytes, baris, dari: str, sampai: str) -> tuple[bytes, int, list]:
    """Hapus isi sel potongan pada baris anggota. Gaya sel dipertahankan, formula tidak disentuh."""
    root = etree.fromstring(sheet_xml)
    sd = root.find("m:sheetData", NS)
    awal, akhir, baris = _kol_ke_angka(dari), _kol_ke_angka(sampai), set(baris)
    n, formula = 0, []
    for row in sd.findall("m:row", NS):
        if int(row.get("r")) not in baris:
            continue
        for c in row.findall("m:c", NS):
            kol = re.match(r"[A-Z]+", c.get("r") or "")
            if not kol or not awal <= _kol_ke_angka(kol.group()) <= akhir:
                continue
            if c.find("m:f", NS) is not None:
                formula.append(c.get("r"))  # jangan pernah menimpa formula
                continue
            if len(c):
                for anak in list(c):
                    c.remove(anak)
                c.attrib.pop("t", None)
                n += 1
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True), n, formula


def _ganti_bulan(xml: bytes, lama: str, baru: str) -> tuple[bytes, int]:
    root = etree.fromstring(xml)
    n = 0
    for t in root.iter(f"{{{NS_MAIN}}}t"):
        if t.text and lama in t.text:
            t.text = t.text.replace(lama, baru)
            n += 1
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True), n


def _buang_nilai_rumus(xml: bytes) -> tuple[bytes, int]:
    """Buang hasil hitung yang tersimpan di sel berformula, supaya pasti dihitung ulang.

    `fullCalcOnLoad` hanya dipatuhi Excel. LibreOffice menampilkan nilai tersimpan apa adanya,
    sehingga JUMLAH POTONGAN, YBS, REKAP, dan STRUK tetap menunjukkan angka bulan lalu padahal
    GAJI dan potongannya sudah berubah. Sel berformula tanpa nilai tersimpan selalu dihitung.
    """
    root = etree.fromstring(xml)
    n = 0
    for c in root.iter(f"{{{NS_MAIN}}}c"):
        if c.find("m:f", NS) is None:
            continue
        v = c.find("m:v", NS)
        if v is not None:
            c.remove(v)
            c.attrib.pop("t", None)
            n += 1
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True), n


def _hitung_ulang_saat_dibuka(xml: bytes) -> bytes:
    root = etree.fromstring(xml)
    calc = root.find("m:calcPr", NS)
    if calc is None:
        calc = etree.SubElement(root, f"{{{NS_MAIN}}}calcPr")
    calc.set("fullCalcOnLoad", "1")
    return etree.tostring(root, xml_declaration=True, encoding="UTF-8", standalone=True)


def tulis_buku(sumber: Path, tujuan: Path, update_baris: dict, bulan_lama, bulan_baru,
               tpl: dict = None, baris_kosongkan=(), teks=None, angka=None):
    """baris_kosongkan: baris anggota yang kolom potongannya (template "kosongkan") dihapus isinya.
    teks / angka: {sel: nilai} tambahan di sheet pembukuan, dipakai saat template kosong diisi
    dari DPP (nama, pangkat, NRP, rekening, nomor urut)."""
    tpl = tpl or muat_template()
    kol_gaji = tpl["kolom"]["gaji"]
    ks = tpl["kosongkan"]
    perlu_ganti = bool(bulan_baru and bulan_lama and bulan_baru != bulan_lama)
    with zipfile.ZipFile(sumber) as zin:
        p_buku = _path_sheet(zin, tpl["sheet"])
        nilai = {**(angka or {}), **{f"{kol_gaji}{r}": v for r, v in update_baris.items()}}
        info = {"sel_formula_dilewati": [], "label_bulan_diganti": 0,
                "label_bulan_inline": 0, "label_bulan_perlu_ganti": perlu_ganti,
                "potongan_dikosongkan": 0, "potongan_formula_dilewati": [],
                "nilai_rumus_dibuang": 0, "isian_formula_dilewati": []}
        with zipfile.ZipFile(tujuan, "w", zipfile.ZIP_DEFLATED) as zout:
            for item in zin.infolist():
                data = zin.read(item.filename)
                if item.filename == p_buku:
                    if ks["dari"] and baris_kosongkan:
                        data, info["potongan_dikosongkan"], info["potongan_formula_dilewati"] = \
                            _kosongkan(data, baris_kosongkan, ks["dari"], ks["sampai"])
                    data, lewat = _set_angka(data, nilai)
                    if teks:
                        data, lewat_teks = _set_teks(data, teks)
                        lewat += lewat_teks
                    # Hanya formula di kolom GAJI yang perlu dilaporkan; di kolom lain (mis. nama
                    # rekening berisi =B10) formula memang sudah menghasilkan isinya sendiri.
                    for ref in lewat:
                        kunci = ("sel_formula_dilewati" if re.match(r"[A-Z]+", ref).group() == kol_gaji
                                 else "isian_formula_dilewati")
                        info[kunci].append(ref)
                if perlu_ganti and item.filename == "xl/sharedStrings.xml":
                    data, n = _ganti_bulan(data, bulan_lama, bulan_baru)
                    info["label_bulan_diganti"] += n
                elif perlu_ganti and RE_WORKSHEET.match(item.filename):
                    # Teks yang disimpan sebagai inline string tidak lewat sharedStrings.xml.
                    calon, n = _ganti_bulan(data, bulan_lama, bulan_baru)
                    if n:        # bila tidak ada yang berubah, byte aslinya dipertahankan
                        data = calon
                        info["label_bulan_inline"] += n
                        info["label_bulan_diganti"] += n
                elif item.filename == "xl/workbook.xml":
                    data = _hitung_ulang_saat_dibuka(data)
                if RE_WORKSHEET.match(item.filename) and b"<f" in data:
                    calon, n = _buang_nilai_rumus(data)
                    if n:        # sheet tanpa formula tetap byte aslinya
                        data = calon
                        info["nilai_rumus_dibuang"] += n
                zout.writestr(item, data)
    return info
