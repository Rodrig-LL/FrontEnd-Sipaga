"""
OTOMASI PDF DPP GAJI -> EXCEL PEMBUKUAN

Pemakaian:
  python jalankan.py --pdf DPP_MASUK --buku PEMBUKUAN_LALU/PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx
  (opsional) --bulan "NOVEMBER 2026"   --keluar HASIL

Tanpa argumen, program memakai folder standar di sebelah file ini:
  DPP_MASUK/        -> taruh semua PDF DPP bulan ini
  PEMBUKUAN_LALU/   -> taruh 1 file pembukuan bulan sebelumnya (.xlsx)
  HASIL/            -> pembukuan baru + laporan validasi muncul di sini

Isi keempat tahapnya ada di proses(), supaya aplikasi.py (antarmuka drag-and-drop) memakai
jalur yang sama persis dengan baris perintah ini. main() hanya pembungkus baris perintah.
"""
import argparse
import re
import sys
from datetime import datetime
from pathlib import Path

from pypdf.errors import PyPdfError

from dpp_parser import baca_dpp
from laporan import buat_laporan
from nominatif import periksa as periksa_personel
from pembukuan import (_kotak, baca_buku, cocokkan, muat_template, pastikan_xlsx, susun_dari_dpp,
                       tulis_buku)

DASAR = Path(__file__).resolve().parent


def siapkan_template(path=None, log=print):
    """Muat template tata letak lalu laporkan asalnya. Dipanggil sebelum apa pun dibaca."""
    tpl = muat_template(path)
    log(f"Tata letak pembukuan: {tpl['_sumber']}")
    for c in tpl["_catatan"]:
        log(f"      catatan template: {c}")
    return tpl


def proses(pdfs, buku_path, keluar, bulan=None, tpl=None, timpa=False, log=print, personel=None,
           potongan=None):
    """Jalankan 4 tahap: baca PDF, baca pembukuan, cocokkan, tulis hasil.

    pdfs      : daftar path berkas PDF DPP
    buku_path : path pembukuan bulan sebelumnya
    keluar    : folder tujuan
    bulan     : paksa nama bulan, mis. "NOVEMBER 2026" (default: dibaca dari PDF)
    tpl       : template tata letak dari siapkan_template()
    timpa     : izinkan menimpa berkas yang sudah ada di folder tujuan
    log       : penerima baris kemajuan, satu baris per panggilan
    personel  : acuan dari database personel (personel_db.acuan()); None = tidak dicocokkan.
                Hasilnya hanya di kunci "pencocokan", tidak masuk buku maupun laporan.
    potongan  : potret data potongan (potongan.snapshot()); None = tanpa Daftar Potongan. Bila ada,
                berkas ketiga DAFTAR_POTONGAN_... ditulis dan ringkasannya di kunci "potongan".
                Buku dan laporan tidak berubah karenanya.

    Mengembalikan dict ringkasan. Kegagalan yang tidak bisa dilanjutkan dilempar sebagai
    SystemExit berisi pesan Indonesia yang sudah siap ditampilkan.
    """
    tpl = tpl or siapkan_template(log=log)
    pdfs = [Path(p) for p in pdfs]
    keluar = Path(keluar)

    # 1. Baca semua PDF DPP
    log(f"[1/4] Membaca {len(pdfs)} PDF DPP ...")
    dpp_files = []
    for p in pdfs:
        try:
            dpp_files.append(baca_dpp(p, log=log))
        except PyPdfError as e:
            raise SystemExit(_kotak(
                "file PDF tidak bisa dibuka",
                [f"File  : {p.name}", f"Galat : {type(e).__name__}: {e}"],
                ["File ini rusak, terpotong saat disalin, terkunci kata sandi, atau bukan PDF",
                 "sungguhan. Ambil ulang dari aplikasi gaji, lalu jalankan lagi."])) from None
    for h in dpp_files:
        bad = sum(1 for x in h["anggota"] if x["masalah"])
        lc = sum(l["cocok"] for l in h["lembar"])
        log(f"      {h['file']:<32} {len(h['anggota']):>4} anggota | subtotal cocok {lc}/{len(h['lembar'])}"
            f" | tidak konsisten {bad}" + (f" | GALAT {len(h['galat'])}" if h["galat"] else "")
            + (f" | hasil scan (OCR) {h['halaman_scan']} hal" if h["halaman_scan"] else ""))
        for g in h["galat"]:
            log(f"      !! {g}")
        if not h["anggota"] and not h["galat"]:
            log(f"      PERINGATAN: {h['file']} tidak menghasilkan satu pun anggota (bukan PDF DPP?).")
    bulan_pdf = {h["bulan"] for h in dpp_files if h["bulan"]}
    if len(bulan_pdf) > 1:
        raise SystemExit(f"PDF berasal dari bulan berbeda: {sorted(bulan_pdf)}. Pisahkan dulu.")
    bulan_dari_pdf = next(iter(bulan_pdf), "")
    bulan_baru = (bulan or bulan_dari_pdf).upper().strip()
    if bulan and bulan_dari_pdf and bulan_baru != bulan_dari_pdf:
        log(f"      PERINGATAN: bulan {bulan_baru} berbeda dengan bulan di PDF ({bulan_dari_pdf}).")
    # Satu proses = satu bank, karena hasilnya satu pembukuan bank (keputusan juru bayar 16 Okt 2026).
    bank_pdf = sorted({h["bank"] for h in dpp_files if h.get("bank")})
    if len(bank_pdf) > 1:
        raise SystemExit(_kotak(
            "PDF dari bank berbeda",
            [f"  - {h['file']}: {h['bank']}" for h in dpp_files if h.get("bank")],
            ["Hasil satu proses adalah satu pembukuan bank, jadi PDF-nya harus dari satu bank.",
             "Pisahkan PDF-nya per bank, lalu proses satu per satu."]))
    dpp = [x for h in dpp_files for x in h["anggota"]]

    # 2. Baca buku bulan lalu
    log("[2/4] Membaca pembukuan sumber ...")
    sumber = pastikan_xlsx(Path(buku_path))
    buku = baca_buku(sumber, tpl, boleh_kosong=True)
    # Pencocokan dan statistik menghitung dengan nilai GAJI lama; teks di sana (mis. "-")
    # dulu menjatuhkan proses dengan pesan Python mentah.
    gaji_teks = [b for b in buku["anggota"] if isinstance(b["gaji_lama"], str)]
    if gaji_teks:
        raise SystemExit(_kotak(
            "kolom GAJI berisi teks",
            [f"Sheet : {tpl['sheet']} ({Path(buku_path).name})",
             "Sel berikut berisi teks, padahal harus angka atau kosong:"],
            [f"  - {tpl['kolom']['gaji']}{b['baris']} : {b['gaji_lama'][:20]!r}   ({b['nama']})"
             for b in gaji_teks[:10]]
            + ([f"  ... dan {len(gaji_teks) - 10} sel lain"] if len(gaji_teks) > 10 else []),
            ["Kosongkan sel itu atau isi dengan angka (0 bila memang belum ada gajinya),",
             "lalu jalankan lagi."]))
    kosong = not buku["anggota"]
    log(f"      {len(buku['anggota'])} anggota di sheet {tpl['sheet']} "
        f"(header baris {buku['header']}), bulan tertulis: {buku['bulan']}")
    if kosong:
        log(f"      Buku ini TEMPLATE KOSONG: tabel diisi dari PDF mulai baris {buku['baris_pertama']}.")
    if not buku["bulan"]:
        log(f"      PERINGATAN: penanda bulan {tpl['label_bulan']['teks']!r} tidak ditemukan "
            "di atas baris header, jadi label bulan di workbook TIDAK akan diganti.")

    # 3. Cocokkan
    teks = angka = None
    if kosong:
        log("[3/4] Menyusun isi tabel dari PDF ...")
        update, teks, angka, temuan, pasangan = susun_dari_dpp(dpp, buku, tpl)
        dpp_tanpa_buku = []
        buku["anggota"] = [b for b, _, _ in pasangan]
        temuan.append({"tingkat": "INFO", "kategori": "Template kosong diisi dari PDF", "baris": None,
                       "nrp": "", "nama": "",
                       "detail": f"{len(pasangan)} anggota ditulis ke sheet {tpl['sheet']} baris "
                                 f"{buku['baris_pertama']}-{buku['baris_pertama'] + len(pasangan) - 1}: "
                                 "nomor, nama, pangkat, korps, NRP, gaji, dan nomor rekening. "
                                 "Potongan dibiarkan kosong. Penanda (K) dan gelar yang tidak ada "
                                 "di PDF perlu dilengkapi manual."})
    else:
        log("[3/4] Mencocokkan NRP ...")
        update, temuan, pasangan, dpp_tanpa_buku = cocokkan(buku["anggota"], dpp, buku["keluar"])
    tidak_di_dpp = [b for b in buku["anggota"] if b["baris"] not in update]

    # Kegagalan membaca PDF tidak boleh berhenti di jendela konsol saja: masuk laporan juga.
    for h in dpp_files:
        for g in h["galat"]:
            temuan.append({"tingkat": "TINGGI", "kategori": "Gagal membaca data dari PDF",
                           "baris": None, "nrp": "", "nama": "",
                           "detail": f"{h['file']}: {g} Akibatnya ada data DPP yang TIDAK ikut "
                                     "diproses dan tidak muncul di sheet DATA DPP. Periksa PDF "
                                     "tersebut, lalu cocokkan anggota itu secara manual."})

    # PDF yang tidak menghasilkan satu pun anggota (surat pengantar, DPP berformat lain) dulu lewat
    # tanpa jejak: bukan galat, bukan temuan. Yang sudah punya galat tidak dicatat dua kali.
    for h in dpp_files:
        if not h["anggota"] and not h["galat"]:
            temuan.append({"tingkat": "TINGGI", "kategori": "PDF tidak berisi data DPP",
                           "baris": None, "nrp": "", "nama": "",
                           "detail": f"{h['file']}: tidak ada satu pun anggota yang terbaca dari PDF "
                                     "ini. Berkasnya mungkin bukan DPP (misalnya surat pengantar) atau "
                                     "formatnya tidak dikenali. Kalau seharusnya berisi anggota, gaji "
                                     "mereka TIDAK ikut diproses."})

    # Data yang dibaca OCR dari hasil scan harus terlihat di laporan: angkanya tercek silang,
    # tetapi nama, NRP, pangkat, dan nomor rekening tidak.
    baris_dari = {id(d): b["baris"] for b, d, _ in pasangan}
    tanpa_buku = {id(d) for d in dpp_tanpa_buku}
    sebut = {"nama": "nama", "nrp": "NRP", "pangkat": "pangkat", "rekening": "nomor rekening"}
    for h in dpp_files:
        dari_scan = [a for a in h["anggota"] if a.get("ocr")]
        if not h["halaman_scan"]:
            continue
        temuan.append({"tingkat": "SEDANG", "kategori": "PDF hasil scan dibaca dengan OCR",
                       "baris": None, "nrp": "", "nama": "",
                       "detail": f"{h['file']}: {h['halaman_scan']} halaman tidak punya lapisan teks dan "
                                 f"dibaca dengan OCR ({len(dari_scan)} anggota). Angkanya sudah dicek "
                                 "silang lewat hitungan per anggota dan JUMLAH LEMBAR, tetapi nama, NRP, "
                                 "pangkat, dan nomor rekening TIDAK punya cek silang. "
                                 + ("Tabel buku disusun dari PDF ini, jadi cocokkan kolom nama, NRP, dan "
                                    "nomor rekening di buku hasil dengan kertas aslinya sebelum transfer."
                                    if kosong else
                                    "Bila ada, lebih baik pakai PDF asli dari aplikasi gaji.")})
        for c in h["catatan_scan"]:
            temuan.append({"tingkat": "SEDANG", "kategori": "Resolusi scan rendah", "baris": None,
                           "nrp": "", "nama": "", "detail": f"{h['file']}: {c}"})
        # Angka yang dilengkapi dari hitungan tidak lagi tercek oleh hitungan itu sendiri, jadi
        # JUMLAH LEMBAR adalah pemeriksaan terakhirnya. Yang tidak cocok harus terlihat jelas.
        for lb in h["lembar"]:
            if lb.get("ocr") and not lb["cocok"]:
                temuan.append({"tingkat": "TINGGI", "kategori": "JUMLAH LEMBAR hasil scan tidak cocok",
                               "baris": None, "nrp": "", "nama": "",
                               "detail": f"{h['file']} hal {lb['halaman']}: jumlah {lb['anggota']} anggota "
                                         f"di lembar ini tidak sama dengan baris JUMLAH LEMBAR pada "
                                         f"{len(lb['beda'])} kolom (" + "; ".join(lb["beda"][:3])
                                         + (" ..." if len(lb["beda"]) > 3 else "")
                                         + "). Ada angka yang salah baca di lembar ini, pada anggotanya "
                                         "atau pada baris jumlahnya. Cocokkan seluruh lembar dengan "
                                         "kertas aslinya."})
        for a in dari_scan:
            asal = f"{h['file']} hal {a['halaman']} no.{a['no_urut']}: "
            if a["ocr"]["dihitung"]:
                temuan.append({"tingkat": "SEDANG", "kategori": "Angka hasil scan dilengkapi dari hitungan",
                               "baris": baris_dari.get(id(a)), "nrp": a["nrp"], "nama": a["nama"],
                               "detail": asal + "; ".join(a["ocr"]["dihitung"])
                                         + ". Cocokkan dengan kertas aslinya."})
            # Yang sudah cocok dengan buku lewat NRP tidak perlu diragukan lagi: bukunya pembanding.
            if a["ocr"]["ragu"] and (kosong or id(a) in tanpa_buku):
                temuan.append({"tingkat": "SEDANG", "kategori": "Bacaan scan meragukan",
                               "baris": baris_dari.get(id(a)), "nrp": a["nrp"], "nama": a["nama"],
                               "detail": asal + "dua kali pembacaan OCR tidak sepakat untuk "
                                         + ", ".join(sebut[k] for k in a["ocr"]["ragu"])
                                         + ". Cocokkan dengan kertas aslinya."})

    bank = re.search(r"(?<![A-Z])(BRI|BSI|BTN|BNI|BJB|MANDIRI)(?![A-Z])", sumber.stem.upper())
    cek = None
    if personel is not None:
        # Bank DPP: dari judul DPP; bila tidak tercetak (DPP lama, hasil scan), dari nama pembukuan.
        bank_cek, dari = ((bank_pdf[0], "DPP") if bank_pdf else
                          (bank.group(1), "pembukuan") if bank else (None, ""))
        cek = periksa_personel(personel, dpp, bank_cek, dari)
        c = cek["hitung"]
        log(f"      Database personel: {cek['aktif']} {cek['jenis']} aktif, {cek['keluar']} keluar; "
            f"bank DPP: {bank_cek or 'tidak diketahui'}" + (f" (dari {dari})" if dari else ""))
        log(f"      Pencocokan: cocok {c['COCOK']} | kurang {c['KURANG']} | cek bank {c['CEK_BANK']} | "
            f"keluar tapi dibayar {c['KELUAR_DIBAYAR']} | lebih {c['LEBIH']} | NRP beda {c['NRP_BEDA']} | "
            f"nama beda {c['NAMA_BEDA']} | rekening beda {c['REKENING_BEDA']} | "
            f"bank lain {c['BUKAN_BANK_INI']}")

    # 4. Tulis hasil
    keluar.mkdir(parents=True, exist_ok=True)
    label = bulan_baru.replace(" ", "_") or "BARU"
    out_buku = keluar / f"PEMBUKUAN_GAJI_{bank.group(1) if bank else 'BRI'}_BLN_{label}.xlsx"
    out_lap = keluar / f"LAPORAN_VALIDASI_DPP_{label}.xlsx"
    bank_daftar = bank_pdf[0] if bank_pdf else (bank.group(1) if bank else None)
    out_pot = (keluar / f"DAFTAR_POTONGAN_{bank_daftar or 'BANK'}_BLN_{label}.xlsx"
               if potongan is not None else None)
    # T3: yang dilindungi bukan hanya file sumber, tetapi juga apa pun yang sudah ada
    # di folder tujuan - termasuk hasil bulan ini yang mungkin sudah disunting di Excel.
    if out_buku.resolve() == sumber.resolve():
        raise SystemExit(_kotak(
            "file hasil akan menimpa file sumber",
            [f"Sumber : {sumber}", f"Hasil  : {out_buku}"],
            "Gunakan folder tujuan yang lain, contohnya HASIL."))
    if keluar.resolve() == sumber.parent.resolve() and not timpa:
        raise SystemExit(_kotak(
            "folder tujuan sama dengan folder pembukuan sumber",
            [f"Folder tujuan : {keluar}",
             f"Pembukuan sumber ada di folder itu juga: {sumber.name}"],
            ["Menulis hasil ke folder yang sama berisiko menimpa pembukuan bulan lalu,",
             "dan bulan depan folder PEMBUKUAN_LALU jadi berisi lebih dari satu buku.",
             "Pakai folder lain, contohnya --keluar HASIL."]))
    sudah_ada = [q for q in (out_buku, out_lap, out_pot) if q and q.exists()]
    if sudah_ada and not timpa:
        raise SystemExit(_kotak(
            "file hasil sudah ada di folder tujuan",
            f"Folder tujuan : {keluar}",
            "File yang sudah ada di sana:",
            [f"  - {q.name}   (terakhir diubah "
             f"{datetime.fromtimestamp(q.stat().st_mtime):%d-%m-%Y %H:%M})"
             for q in sudah_ada],
            ["Pindahkan atau hapus file di atas, lalu jalankan ulang. Ini cara paling aman,",
             "terutama bila file itu sudah pernah dibuka dan disimpan ulang di Excel.",
             "",
             "Kalau memang ingin menimpanya, jalankan dengan tambahan  --timpa",
             "(isi file lama hilang dan tidak bisa dikembalikan)."]))
    log("[4/4] Menulis file hasil ...")
    info = tulis_buku(sumber, out_buku, update, buku["bulan"], bulan_baru, tpl,
                      baris_kosongkan=[b["baris"] for b in buku["anggota"]],
                      teks=teks, angka=angka)
    ks = tpl["kosongkan"]
    if ks["dari"]:
        log(f"      {info['potongan_dikosongkan']} sel potongan dikosongkan "
            f"(kolom {ks['dari']}-{ks['sampai']}, {len(buku['anggota'])} baris anggota).")
    if info["potongan_formula_dilewati"]:
        f = info["potongan_formula_dilewati"]
        temuan.append({"tingkat": "SEDANG", "kategori": "Sel potongan berisi formula", "baris": None,
                       "nrp": "", "nama": "",
                       "detail": f"{len(f)} sel potongan berisi formula sehingga tidak dikosongkan: "
                                 + ", ".join(f[:12]) + (" ..." if len(f) > 12 else "")
                                 + ". Periksa apakah nilainya masih berlaku bulan ini."})
    if info["label_bulan_perlu_ganti"] and info["label_bulan_diganti"] == 0:
        log(f"      PERINGATAN: teks {buku['bulan']!r} tidak ditemukan di dalam workbook, "
            "jadi tidak ada label bulan yang terganti.")
        temuan.append({"tingkat": "SEDANG", "kategori": "Label bulan tidak terganti",
                       "baris": None, "nrp": "", "nama": "",
                       "detail": f"Bulan di buku sumber terbaca {buku['bulan']!r}, tetapi teks "
                                 f"itu tidak ditemukan di dalam workbook, sehingga tidak ada "
                                 f"label yang berubah menjadi {bulan_baru!r}. Periksa dan ubah "
                                 "sendiri judul/label bulan di file pembukuan baru."})
    elif info["label_bulan_inline"]:
        log(f"      {info['label_bulan_inline']} label bulan diganti pada sel inline string.")

    for ref in info["sel_formula_dilewati"]:
        temuan.append({"tingkat": "SEDANG", "kategori": "Sel GAJI berisi formula",
                       "baris": int(re.search(r"\d+", ref).group()),
                       "nrp": "", "nama": "", "detail": f"{ref} berisi formula, tidak ditimpa."})

    statistik = {
        "buku_total": len(buku["anggota"]), "cocok": len(update),
        "gaji_berubah": sum(1 for b, d, _ in pasangan if round(b["gaji_lama"]) != d["dibayarkan"]),
        "tidak_di_dpp": len(tidak_di_dpp), "nilai_tidak_di_dpp": sum(b["gaji_lama"] for b in tidak_di_dpp),
        "dpp_tanpa_buku": len(dpp_tanpa_buku), "nilai_dpp_tanpa_buku": sum(d["dibayarkan"] for d in dpp_tanpa_buku),
        "total_update": sum(update.values()), "total_dpp_cocok": sum(d["dibayarkan"] for _, d, _ in pasangan),
        "label_bulan": info["label_bulan_diganti"],
    }
    buat_laporan(out_lap, {"bulan_baru": bulan_baru, "buku_sumber": Path(buku_path).name,
                           "buku_hasil": out_buku.name, "dpp_files": dpp_files, "temuan": temuan,
                           "pasangan": pasangan, "statistik": statistik})

    # Daftar Potongan (keputusan juru bayar 16-17 Okt 2026): berkas terpisah, buku tetap dikosongkan.
    pot = None
    if potongan is not None:
        from potongan import daftar_proses
        pot = daftar_proses(potongan, sumber, tpl, buku, update, dpp_tanpa_buku, bulan_baru, bank_daftar,
                            out_pot, log=log)

    return {"dpp_files": dpp_files, "buku": buku, "bulan_baru": bulan_baru, "temuan": temuan,
            "pasangan": pasangan, "statistik": statistik, "info_tulis": info, "sumber": sumber,
            "out_buku": out_buku, "out_lap": out_lap, "pencocokan": cek, "potongan": pot, "out_pot": out_pot,
            "tingkat": {k: sum(1 for t in temuan if t["tingkat"] == k)
                        for k in ("TINGGI", "SEDANG", "INFO")}}


def main():
    ap = argparse.ArgumentParser(description="PDF DPP gaji -> Excel pembukuan")
    ap.add_argument("--pdf", default=DASAR / "DPP_MASUK", type=Path)
    ap.add_argument("--buku", type=Path, help="file pembukuan bulan sebelumnya")
    ap.add_argument("--bulan", help='contoh "NOVEMBER 2026" (default: dibaca dari PDF)')
    ap.add_argument("--keluar", default=DASAR / "HASIL", type=Path)
    ap.add_argument("--template", type=Path,
                    help="tata letak pembukuan (default: template_pembukuan.json)")
    ap.add_argument("--timpa", action="store_true",
                    help="izinkan menimpa file yang sudah ada di folder tujuan")
    a = ap.parse_args()

    # Template dibaca paling awal: tata letak yang salah tidak perlu menunggu PDF dibaca.
    tpl = siapkan_template(a.template)

    if a.buku is None:
        kandidat = sorted((DASAR / "PEMBUKUAN_LALU").glob("*.xls*"))
        if len(kandidat) != 1:
            sys.exit("Taruh tepat 1 file pembukuan bulan lalu di folder PEMBUKUAN_LALU (atau pakai --buku).")
        a.buku = kandidat[0]
    pdfs = sorted(Path(a.pdf).glob("*.pdf")) if Path(a.pdf).is_dir() else [Path(a.pdf)]
    if not pdfs:
        sys.exit(f"Tidak ada PDF di {a.pdf}")

    h = proses(pdfs, a.buku, a.keluar, bulan=a.bulan, tpl=tpl, timpa=a.timpa)

    tinggi = h["tingkat"]["TINGGI"]
    print(f"\nSELESAI. Gaji diperbarui: {h['statistik']['cocok']} | "
          f"gaji berubah: {h['statistik']['gaji_berubah']} | temuan TINGGI: {tinggi}")
    print(f"  -> {h['out_buku']}\n  -> {h['out_lap']}")
    if tinggi:
        print("  PERHATIAN: buka sheet TEMUAN di laporan sebelum file dipakai untuk transfer.")


if __name__ == "__main__":
    main()
