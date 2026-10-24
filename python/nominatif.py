"""
Nominatif dan pencocokan DPP dengan database personel.

Dua bagian:
- baca_nominatif(): membaca berkas Excel nominatif (daftar personel satuan). Sejak 16 Okt 2026 ia
  hanya dipakai halaman impor database personel (personel_db.rencana_nominatif); kotak seret-lepas
  nominatif di langkah 1 sudah dihapus.
- periksa(): mencocokkan anggota DPP dengan personel di database (personel_db.acuan()). Database
  adalah acuan: yang tercatat aktif tetapi tidak dibayar (KURANG), yang dibayar tetapi tidak
  tercatat (LEBIH), dan yang tercatat tetapi NRP, nama, atau rekeningnya berbeda. Hasilnya hanya
  ditampilkan di langkah "Periksa hasil baca" SIPAGA; tidak masuk pembukuan maupun laporan Excel.

DPP datang per bank. Bank DPP dibaca dari judul DPP ("PEMBAYARAN : ... BRI ..."), atau dari nama
berkas pembukuan bila judulnya tidak menyebut bank. Bank tiap personel di database yang menentukan
apakah ia seharusnya ada di DPP itu (keputusan juru bayar 16 Okt 2026, bagian 4h dokumen konteks).

Baru untuk militer. PNS sudah tersimpan di database, tetapi belum dicocokkan karena pembaca DPP PNS
belum ada.
"""
import re
from pathlib import Path

import openpyxl

from pembukuan import AMBANG_NAMA, _kotak, digit, mirip, pastikan_xlsx

CARI_HEADER = 30          # baris teratas tiap sheet yang diperiksa untuk mencari judul kolom
MIN_DIGIT = 5             # sel NRP/NIP dengan angka lebih sedikit dari ini bukan baris personel
# Judul kolom (huruf besar, tanpa spasi) -> peran. "N A M A" dan "PANGKAT/\nKORPS" ikut kena.
# Cocok persis, kecuali PANGKAT (awalan) - "NAMA PERGURUAN TINGGI" di nominatif PNS bukan nama.
PERAN = {"NAMA": "nama", "NRP": "id", "NIP": "id", "NRP/NIP": "id", "GOL": "gol",
         "JABATAN": "jabatan", "KET": "ket", "TANGGALLAHIR": "tgl_lahir"}
# Urutan tampil di log: yang harus ditindaklanjuti dulu, COCOK terakhir. CEK_BANK = tidak ada di
# DPP, tetapi bank-nya belum diisi di database atau bank DPP tidak diketahui; tetap peringatan,
# supaya yang benar-benar kurang tidak tersembunyi.
URUT = ["KURANG", "CEK_BANK", "KELUAR_DIBAYAR", "LEBIH", "NRP_BEDA", "NAMA_BEDA", "REKENING_BEDA",
        "BUKAN_BANK_INI", "COCOK"]


def _norm(v) -> str:
    return re.sub(r"\s+", "", str(v if v is not None else "")).upper()


def _teks(v) -> str:
    return "" if v is None else str(v).replace("\n", " ").strip()


def _id(v):
    """Isi sel NRP/NIP sebagai teks. Angka dari Excel diubah tanpa '.0'; angka 16 digit ke atas
    yang tersimpan sebagai bilangan bisa sudah kehilangan digit akhirnya (kejadian nyata pada
    NIP di BUKU PNS), jadi dikembalikan juga penandanya."""
    if isinstance(v, float) and v.is_integer():
        v = int(v)
    s = _teks(v)
    return s, isinstance(v, (int, float)) and len(digit(s)) >= 16


def _peta_header(baris) -> dict:
    kol = {}
    for i, v in enumerate(baris):
        n = _norm(v)
        peran = PERAN.get(n) or ("pangkat" if n.startswith("PANGKAT") else None)
        if peran and peran not in kol:
            kol[peran] = i
    return kol


def baca_nominatif(path) -> dict:
    """Baca satu berkas nominatif.

    Sheet yang dipakai = sheet PERTAMA yang punya baris judul kolom berisi NAMA dan NRP
    (militer) atau NIP (PNS). Sheet sesudahnya diabaikan: berkas nominatif asli menyimpan versi
    lama di sheet berikutnya (MIL_jab = Januari 2026), dan nominatif PNS memuat daftar Kodam lain.
    Nama sheet dan judulnya ikut dikembalikan supaya terlihat di log acuan mana yang dipakai.
    """
    path = Path(path)
    try:
        wb = openpyxl.load_workbook(pastikan_xlsx(path), read_only=True, data_only=True)
    except SystemExit:
        raise
    except Exception as e:                                     # noqa: BLE001
        raise SystemExit(_kotak(
            "berkas nominatif tidak bisa dibuka",
            [f"Berkas : {path.name}", f"Galat  : {type(e).__name__}: {e}"],
            ["Pastikan yang diseret ke kotak Nominatif adalah berkas Excel (.xlsx/.xls) yang utuh,",
             "lalu proses lagi."])) from None
    try:
        for ws in wb.worksheets:
            rows = list(ws.iter_rows(values_only=True))
            h = next((i for i, r in enumerate(rows[:CARI_HEADER])
                      if {"nama", "id"} <= set(_peta_header(r))), None)
            if h is None:
                continue
            kol = _peta_header(rows[h])
            jenis = "pns" if _norm(rows[h][kol["id"]]) == "NIP" else "militer"
            judul = [" ".join(_teks(v) for v in r if _teks(v) and set(_teks(v)) - set("_- "))
                     for r in rows[:h]]
            judul = " · ".join([j for j in judul if j][-2:])

            def sel(r, peran):
                i = kol.get(peran)
                return r[i] if i is not None and i < len(r) else None

            orang, catatan, seksi = [], [], ""
            for no, r in enumerate(rows[h + 1:], start=h + 2):
                nrp, angka = _id(sel(r, "id"))
                if len(digit(nrp)) < MIN_DIGIT:
                    # Judul seksi, mis. "NOMINATIF BP KE MADITAJENAD"; header ulang dan baris
                    # nomor kolom di bawahnya ikut terlewati di sini.
                    awal = next((_teks(v) for v in r if _teks(v)), "")
                    if _norm(awal).startswith("NOMINATIF"):
                        seksi = awal
                    continue
                if angka:
                    catatan.append(f"baris {no}: NRP/NIP {nrp} tersimpan sebagai angka; digit akhirnya "
                                   "bisa sudah berubah. Simpan kolom itu sebagai teks.")
                orang.append({"baris": no, "nama": _teks(sel(r, "nama")),
                              "pangkat": _teks(sel(r, "pangkat")) or _teks(sel(r, "gol")),
                              "nrp": digit(nrp), "nrp_asli": nrp, "jabatan": _teks(sel(r, "jabatan")),
                              "ket": _teks(sel(r, "ket")), "tgl_lahir": sel(r, "tgl_lahir"),
                              "seksi": seksi})
            if not orang:
                raise SystemExit(_kotak(
                    "nominatif tidak berisi personel",
                    [f"Berkas : {path.name}, sheet {ws.title}",
                     f"Judul kolom ditemukan di baris {h + 1}, tetapi tidak ada satu pun baris di bawahnya",
                     f"yang kolom {'NIP' if jenis == 'pns' else 'NRP'}-nya berisi minimal {MIN_DIGIT} angka."],
                    "Periksa apakah berkas yang diseret memang nominatif yang sudah terisi."))
            return {"file": path.name, "sheet": ws.title, "judul": judul, "jenis": jenis,
                    "orang": orang, "catatan": catatan}
        raise SystemExit(_kotak(
            "berkas nominatif tidak dikenali",
            [f"Berkas : {path.name}", f"Sheet  : {', '.join(wb.sheetnames)}",
             f"Dicari baris judul kolom berisi NAMA dan NRP (militer) atau NIP (PNS) di {CARI_HEADER}",
             "baris teratas tiap sheet, dan tidak ada yang ketemu."],
            ["Pastikan yang diseret ke kotak Nominatif memang nominatif, dan judul kolomnya",
             "tertulis NAMA serta NRP atau NIP."]))
    finally:
        wb.close()


def _baris(status, o=None, d=None, detail=""):
    pd = " ".join(x for x in ((d or {}).get("pangkat", ""), (d or {}).get("korps", "")) if x)
    return {"status": status, "id": o["id"] if o else None,
            "nama": o["nama"] if o else "", "pangkat": o["pangkat"] if o else "",
            "nrp": o["nrp_asli"] if o else "", "ket": o["ket"] if o else "",
            "seksi": o["seksi"] if o else "", "bank_db": o["bank"] if o else "",
            "rekening_db": o["rekening"] if o else "",
            "nama_dpp": d["nama"] if d else "", "pangkat_dpp": pd, "nrp_dpp": d["nrp"] if d else "",
            "rekening_dpp": digit(d.get("rekening", "")) if d else "",
            "sumber": f"{d['file']} hal {d['halaman']} no. {d['no_urut']}" if d else "",
            "detail": detail}


def _kata1(s) -> str:
    k = str(s or "").split()
    return k[0].upper() if k else ""


def periksa(acuan: dict, dpp: list, bank=None, bank_dari="") -> dict:
    """Cocokkan anggota DPP dengan personel di database.

    acuan     : personel_db.acuan(): {"orang": baris tabel personel, "jenis", "diubah", "lain"}
    dpp       : semua anggota DPP yang terbaca
    bank      : bank DPP yang diproses; None = tidak diketahui
    bank_dari : "DPP" atau "pembukuan", untuk keterangan di layar

    Urutan: (1) NRP sama -> COCOK, NAMA_BEDA bila namanya tidak mirip, REKENING_BEDA bila rekening
    di database terisi dan tidak sama dengan DPP; (2) sisanya, nama mirip -> NRP_BEDA; personel
    keluar yang berpasangan -> KELUAR_DIBAYAR; (3) DPP yang tersisa -> LEBIH; (4) personel aktif
    yang tersisa -> KURANG bila bank-nya di database sama dengan bank DPP, BUKAN_BANK_INI bila bank
    lain, CEK_BANK bila bank-nya belum diisi atau bank DPP tidak diketahui. Personel keluar yang
    memang tidak ada di DPP tidak masuk log.
    """
    orang = acuan["orang"]
    hasil, per_nrp = [], {}
    for d in dpp:
        per_nrp.setdefault(digit(d["nrp"]), []).append(d)

    def pasang(o, d, status, detail):
        r = digit(d.get("rekening", ""))
        beda_rek = bool(o["rekening"]) and r != o["rekening"]
        if o["status"] == "keluar":
            detail = (f"Tercatat keluar sejak {o['tgl_keluar']} ({o['alasan_keluar']}), tetapi masih "
                      "dibayar di DPP." + (f" {detail}" if detail else ""))
            status = "KELUAR_DIBAYAR"
        elif status == "COCOK" and beda_rek:
            status, detail = "REKENING_BEDA", (f"Rekening di DPP {r or '(kosong)'} tidak sama dengan "
                                               f"rekening di database {o['rekening']}.")
        elif beda_rek:
            detail += f" Rekeningnya juga beda: DPP {r or '(kosong)'}, database {o['rekening']}."
        hasil.append(_baris(status, o, d, detail))

    # 1. NRP sama
    dipakai, sisa = set(), []
    for o in orang:
        calon = [d for d in per_nrp.get(o["nrp"], []) if id(d) not in dipakai]
        if not calon:
            sisa.append(o)
            continue
        d = calon[0]
        dipakai.add(id(d))
        s = mirip(o["nama"], d["nama"])
        if s >= AMBANG_NAMA:
            pasang(o, d, "COCOK", "")
        else:
            pasang(o, d, "NAMA_BEDA", f"NRP sama, tetapi nama di database {o['nama']!r} dan di DPP "
                                      f"{d['nama']!r} (kemiripan {s:.2f}). Pastikan orangnya sama.")

    # 2. NRP beda, nama sama. Pasangan dengan kemiripan tertinggi dipasangkan lebih dulu.
    sisa_dpp = [d for d in dpp if id(d) not in dipakai]
    calon = sorted(((mirip(o["nama"], d["nama"]), i, j) for i, o in enumerate(sisa)
                    for j, d in enumerate(sisa_dpp)), key=lambda x: (-x[0], x[1], x[2]))
    o_pakai, d_pakai = set(), set()
    for s, i, j in calon:
        if s < AMBANG_NAMA:
            break
        if i in o_pakai or j in d_pakai:
            continue
        o_pakai.add(i)
        d_pakai.add(j)
        o, d = sisa[i], sisa_dpp[j]
        pn, pdp = _kata1(o["pangkat"]), _kata1(d["pangkat"])
        pasang(o, d, "NRP_BEDA", (
            f"Nama cocok (kemiripan {s:.2f}), tetapi NRP di database {o['nrp_asli']} dan di DPP "
            f"{d['nrp']}. Salah satunya salah tulis."
            + (f" Pangkatnya juga beda (database {o['pangkat']}, DPP {d['pangkat']}): bisa jadi "
               "dua orang yang berbeda bernama sama." if pn and pdp and pn != pdp else "")))

    # 3. Dibayar, tidak tercatat
    for j, d in enumerate(sisa_dpp):
        if j in d_pakai:
            continue
        hasil.append(_baris("LEBIH", d=d, detail=(
            "Dibayar di DPP, tetapi tidak tercatat di database personel, baik lewat NRP maupun nama."
            + (" NRP ini muncul lebih dari sekali di DPP." if len(per_nrp[digit(d["nrp"])]) > 1 else ""))))

    # 4. Tercatat aktif, tidak dibayar
    for i, o in enumerate(sisa):
        if i in o_pakai or o["status"] == "keluar":
            continue
        if not bank:
            hasil.append(_baris("CEK_BANK", o, detail=(
                "Tidak ada di DPP. Bank DPP ini tidak diketahui (judul DPP maupun nama berkas pembukuan "
                "tidak menyebutnya), jadi yang gajinya kurang tidak bisa dibedakan dari yang dibayar "
                "lewat bank lain. Cek di DPP bank lain; kalau tidak ada di sana, gajinya kurang.")))
        elif not o["bank"]:
            hasil.append(_baris("CEK_BANK", o, detail=(
                f"Tidak ada di DPP {bank}, dan bank-nya belum diisi di database. Kurang, atau dibayar "
                "lewat bank lain: lengkapi bank-nya di Data Personel.")))
        elif o["bank"] == bank:
            hasil.append(_baris("KURANG", o, detail=(
                f"Menurut database dibayar lewat {bank}, tetapi tidak ada di DPP {bank}. Gajinya kurang.")))
        else:
            hasil.append(_baris("BUKAN_BANK_INI", o, detail=(
                f"Menurut database dibayar lewat {o['bank']}, bukan {bank}. Pastikan ia ada di DPP {o['bank']}.")))

    hasil.sort(key=lambda x: URUT.index(x["status"]))         # stabil: urutan database terjaga
    aktif = sum(1 for o in orang if o["status"] == "aktif")
    return {"sumber": "database", "jenis": acuan["jenis"], "jumlah": len(orang), "aktif": aktif,
            "keluar": len(orang) - aktif, "diubah": acuan["diubah"], "lain": acuan["lain"],
            "bank": bank, "bank_dari": bank_dari, "baris": hasil,
            "hitung": {k: sum(1 for x in hasil if x["status"] == k) for k in URUT}}
