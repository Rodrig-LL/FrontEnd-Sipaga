"""
Pembaca PDF "Daftar Pembayaran Penghasilan dan ULP Prajurit TNI" (DPP gaji induk).

PDF dari aplikasi gaji sudah memiliki lapisan teks, sehingga dibaca langsung (bukan OCR)
dengan urutan content-stream yang konsisten. Setiap anggota divalidasi silang secara
aritmatika (bruto, kotor, potongan, bersih, dibayarkan) dan setiap lembar dicocokkan
dengan baris JUMLAH LEMBAR di PDF itu sendiri.

Nilai nol boleh tertulis sebagai angka 0 maupun sebagai tanda hubung, dan nilai negatif
boleh bergaya -50, 50-, atau (50); lihat _angka_dari().
"""
import re
from pathlib import Path

from pypdf import PdfReader

# Urutan 35 angka per anggota, sesuai kolom 1-15 di DPP
KOLOM_ANGKA = [
    "g_pokok", "t_istri", "t_anak", "g_bruto",
    "t_struktural", "t_fungsional", "t_umum", "t_beras", "t_kowan", "t_sandi",
    "t_babinsa", "t_papua", "t_pencil", "t_terluar", "t_terampil", "t_lainnya", "tpp",
    "t_pph", "pembulatan",
    "jml_kotor", "p_beras",
    "p_pensiun", "p_bpjs", "p_tht", "p_bpjs_lain", "p_sewa_rumah", "p_utang",
    "p_pph21", "p_pengembalian", "p_tgr", "p_lainnya",
    "jml_potongan", "bersih",
    "lauk_pauk", "dibayarkan",
]
TUNJANGAN = KOLOM_ANGKA[4:17]
POTONGAN = ["p_beras"] + KOLOM_ANGKA[21:31]

RE_HALAMAN = re.compile(r"^:\s*\d+\s*$")
RE_AKHIR = re.compile(r"^\.{5,}\s+(\d+)\.\s*$")
RE_NAMA = re.compile(r"^\s*(\d+)\.\s+(\S.*)$")
RE_NRP = re.compile(r"^(\d{5,17})\s+([A-Z]{2,4})\s*$")
RE_ANGKA = re.compile(r"^\d{1,3}(\.\d{3})*$")
# Sebagian aplikasi gaji mencetak nilai nol sebagai tanda hubung, dan memakai tanda hubung
# berderet sebagai garis pemisah. Tanda hubung sepanjang <= HUBUNG_NOL_MAKS dibaca nol;
# yang lebih panjang dianggap garis dan dilewati. Beberapa PDF memakai en/em dash.
HUBUNG = "-\u2010\u2011\u2012\u2013\u2014\u2015\u2212"
HUBUNG_NOL_MAKS = 3
# Angka negatif dicetak dalam tiga gaya: minus di depan (-50), minus di belakang (50-,
# lazim di cetakan lama), dan tanda kurung ((50)). Dua yang pertama tidak bisa dibedakan
# dari tanda hubung yang menempel ke angka tanpa spasi, jadi tafsirannya diputuskan oleh
# jumlah kolom - lihat _angka_dari().
_ANGKA = r"\d{1,3}(?:\.\d{3})*"
_H = "[" + re.escape(HUBUNG) + "]"
RE_KURUNG = re.compile(r"^\((" + _ANGKA + r")\)$")
RE_TANDA_DEPAN = re.compile("^" + _H + "(" + _ANGKA + ")$")
RE_TANDA_BELAKANG = re.compile("^(" + _ANGKA + ")" + _H + "$")
RE_NOMOR_ID = re.compile(r"^[\d.]{9,}$")
RE_BULAN = re.compile(r"BULAN\s+([A-Z]+\s+\d{4})")
# Bank pembayaran di judul DPP, mis. "PEMBAYARAN : GAJI INDUK  TNI BRI BULAN OKTOBER 2026".
# Dipakai pencocokan dengan database personel untuk tahu DPP ini milik bank mana.
RE_BANK = re.compile(r"PEMBAYARAN\s*:[^\n]*?(?<![A-Z])(BRI|BSI|BTN|BNI|BJB|MANDIRI)(?![A-Z])")
PANGKAT = {"JENDERAL", "LETJEN", "MAYJEN", "BRIGJEN", "KOLONEL", "LETKOL", "MAYOR", "KAPTEN",
           "LETTU", "LETDA", "PELTU", "PELDA", "SERMA", "SERKA", "SERTU", "SERDA",
           "KOPKA", "KOPTU", "KOPDA", "PRAKA", "PRATU", "PRADA", "KLS"}


def rp(s: str) -> int:
    return int(s.replace(".", ""))


def _pisah_token(lines):
    """Pecah area angka menjadi token berjenis.

    ("angka", nilai)          angka biasa, termasuk (50) yang sudah pasti negatif
    ("hubung", panjang)       token yang seluruhnya tanda hubung
    ("tanda_depan", nilai)    -50  : negatif, ATAU tanda hubung lalu 50
    ("tanda_belakang", nilai) 50-  : negatif, ATAU 50 lalu tanda hubung
    """
    out = []
    for ln in lines:
        for tok in ln.split():
            if all(c in HUBUNG for c in tok):
                out.append(("hubung", len(tok)))
            elif RE_ANGKA.match(tok):
                out.append(("angka", rp(tok)))
            elif m := RE_KURUNG.match(tok):
                out.append(("angka", -rp(m.group(1))))
            elif m := RE_TANDA_DEPAN.match(tok):
                out.append(("tanda_depan", rp(m.group(1))))
            elif m := RE_TANDA_BELAKANG.match(tok):
                out.append(("tanda_belakang", rp(m.group(1))))
            else:
                raise ValueError(f"token tak terduga di area angka: {tok!r}")
    return out


def _susun(token, nol_maks, negatif=True):
    """Bangun deret angka dari token.

    nol_maks : tanda hubung sepanjang <= ini dibaca nol; yang lebih panjang = garis pemisah
               dan dilewati.
    negatif  : True  -> "-50" dan "50-" dibaca sebagai nilai negatif (satu angka).
               False -> dibaca sebagai tanda hubung yang menempel ke angka (dua token).
    """
    out = []
    for jenis, v in token:
        if jenis == "angka":
            out.append(v)
        elif jenis == "hubung":
            if v <= nol_maks:
                out.append(0)
        elif negatif:
            out.append(-v)
        elif jenis == "tanda_depan":
            if nol_maks >= 1:        # tanda hubungnya dibaca nol, bukan garis
                out.append(0)
            out.append(v)
        else:                        # tanda_belakang
            out.append(v)
            if nol_maks >= 1:
                out.append(0)
    return out


def _angka_dari(lines, harap=None):
    """Ambil deret angka dari area angka satu anggota (atau satu baris subtotal).

    Tanda hubung tunggal dibaca sebagai nilai nol, tanda hubung berderet dilewati sebagai
    garis pemisah, dan "-50"/"50-" dibaca sebagai angka negatif. Dua aturan terakhir bisa
    bertabrakan, jadi kalau `harap` diberikan dan tafsiran bawaan tidak menghasilkan tepat
    `harap` angka, tafsiran lain dicoba berurutan dan yang pas dipakai. Jumlah kolom DPP
    sendiri yang menjadi juri, bukan tebakan bentuk token. Salah tafsir masih tersaring
    validasi aritmatika per anggota dan pencocokan subtotal per lembar.
    """
    token = _pisah_token(lines)
    terpanjang = max((v for j, v in token if j == "hubung"), default=0)
    tafsiran = [(HUBUNG_NOL_MAKS, True), (0, True), (HUBUNG_NOL_MAKS, False),
                (0, False), (terpanjang, False), (terpanjang, True)]
    hasil = _susun(token, *tafsiran[0])
    if harap is None or len(hasil) == harap:
        return hasil
    for nol_maks, negatif in tafsiran[1:]:
        calon = _susun(token, nol_maks, negatif)
        if len(calon) == harap:
            return calon
    sensus = {}
    for j, v in token:
        if j == "hubung":
            sensus[v] = sensus.get(v, 0) + 1
    n_angka = sum(1 for j, _ in token if j == "angka")
    n_tanda = sum(1 for j, _ in token if j in ("tanda_depan", "tanda_belakang"))
    rinci = ("; " + ", ".join(f"{n}x tanda hubung sepanjang {pj}"
                              for pj, n in sorted(sensus.items()))
             if sensus else "; tanpa tanda hubung")
    raise ValueError(f"jumlah angka {len(hasil)} != {harap} (token angka {n_angka}"
                     + (f", bertanda minus {n_tanda}" if n_tanda else "")
                     + f"{rinci})")


def _parse_anggota(seg, sumber, halaman):
    """seg = baris-baris satu anggota, dari JAB/ESLN sampai baris '.......  N.'"""
    rec = {"file": sumber, "halaman": halaman, "masalah": []}
    rec["jab_esln"], rec["sts_kawin"], rec["tmt_kgb"], rec["mkg"] = (s.strip() for s in seg[:4])

    i_nama = next(i for i, ln in enumerate(seg) if RE_NAMA.match(ln))
    angka = _angka_dari(seg[4:i_nama], harap=len(KOLOM_ANGKA))
    rec.update(zip(KOLOM_ANGKA, angka))

    m = RE_NAMA.match(seg[i_nama])
    rec["no_urut"] = int(m.group(1))
    nama = [m.group(2).strip()]
    j = i_nama + 1
    while not RE_NRP.match(seg[j].strip()):
        nama.append(seg[j].strip())
        j += 1
    rec["nama"] = " ".join(nama)
    rec["nrp"], rec["korps"] = RE_NRP.match(seg[j].strip()).groups()

    tgl = seg[j + 1].split(None, 1)
    rec["tgl_lahir"] = tgl[0]
    tmt = tgl[1].strip() if len(tgl) > 1 else ""
    rec["tmt_jab"] = "" if tmt.replace("-", "").strip() == "" else tmt

    teks, nomor = [], []
    for ln in seg[j + 2:-1]:
        s = ln.strip()
        (nomor if RE_NOMOR_ID.match(s) else teks).append(s)
    rec["rekening"] = nomor[-1] if nomor else ""
    rec["npwp"] = nomor[-2] if len(nomor) > 1 else ""
    kata = " ".join(teks).split()
    if kata and kata[0].upper() in PANGKAT:
        ambil = 2 if len(kata) > 1 and kata[1] in ("TN", "TNI") else 1
        rec["pangkat"] = " ".join(kata[:ambil]).replace(" TN", " TNI").replace("TNII", "TNI")
        rec["jabatan"] = " ".join(kata[ambil:])
    else:
        rec["pangkat"], rec["jabatan"] = "", " ".join(kata)

    _validasi_anggota(rec)
    return rec


def _validasi_anggota(r):
    cek = [
        ("G.BRUTO", r["g_pokok"] + r["t_istri"] + r["t_anak"], r["g_bruto"]),
        ("JUMLAH KOTOR", r["g_bruto"] + sum(r[k] for k in TUNJANGAN) + r["t_pph"] + r["pembulatan"], r["jml_kotor"]),
        ("JUMLAH POTONGAN", sum(r[k] for k in POTONGAN), r["jml_potongan"]),
        ("BERSIH", r["jml_kotor"] - r["jml_potongan"], r["bersih"]),
        ("DIBAYARKAN", r["bersih"] + r["lauk_pauk"], r["dibayarkan"]),
    ]
    for nama, hitung, tertulis in cek:
        if hitung != tertulis:
            r["masalah"].append(f"{nama}: hitung {hitung:,} vs tertulis {tertulis:,}")
    if r["t_pph"] != r["p_pph21"]:
        r["masalah"].append(f"Tunjangan PPh ({r['t_pph']:,}) tidak sama dengan potongan PPh21 ({r['p_pph21']:,})")


def _baca_scan(path, tanpa_teks, teks_asing, hasil, log):
    """Halaman hasil scan dibaca lewat OCR (dpp_ocr.py). Mengembalikan {no_hal: hasil}.

    tanpa_teks : halaman tanpa lapisan teks sama sekali - wajib terbaca, gagal = galat.
    teks_asing : halaman berlapis teks tetapi teksnya tidak berbentuk DPP. Itu ciri scan yang
                 diberi lapisan teks oleh mesin scan-nya ("searchable PDF"), tetapi bisa juga
                 halaman lain yang memang bukan DPP. Dicoba lewat OCR; bila ternyata bukan
                 lembar DPP, halaman itu dilewati tanpa galat, sama seperti perilaku semula.
    """
    from dpp_ocr import GagalOCR, baca_halaman_scan
    if log:
        log(f"      {path.name}: {len(tanpa_teks) + len(teks_asing)} halaman dibaca sebagai hasil scan "
            "dengan OCR (lebih lama, sekitar 3 detik per halaman) ...")
    try:
        dari_scan = baca_halaman_scan(path, sorted(tanpa_teks + teks_asing), log)
    except GagalOCR as e:
        if tanpa_teks:
            hasil["galat"].append(f"{len(tanpa_teks)} halaman tidak memiliki lapisan teks (hasil scan) "
                                  f"dan tidak bisa dibaca: {e}. Gunakan PDF asli dari aplikasi gaji.")
        return {}
    for no in teks_asing:
        if not dari_scan[no]["anggota"] and not dari_scan[no]["lembar"]:
            del dari_scan[no]
    return dari_scan


DPI_MINIMAL = 180


def baca_dpp(path, log=None):
    """Mengembalikan dict: bulan, anggota (list), lembar (list cek subtotal), galat (list),
    halaman_scan (jumlah halaman yang dibaca lewat OCR), catatan_scan (peringatan mutu scan)."""
    path = Path(path)
    reader = PdfReader(str(path))
    hasil = {"file": path.name, "bulan": None, "anggota": [], "lembar": [], "galat": [],
             "halaman_scan": 0, "catatan_scan": [], "bank": None}
    if not reader.pages:
        hasil["galat"].append("PDF tidak berisi halaman.")
        return hasil
    teks_hal = [page.extract_text() or "" for page in reader.pages]
    hasil["bank"] = next((m.group(1) for m in map(RE_BANK.search, teks_hal) if m), None)
    tanpa_teks = [no for no, t in enumerate(teks_hal, 1) if len(t.strip()) < 50]
    teks_asing = [no for no, t in enumerate(teks_hal, 1) if no not in tanpa_teks
                  and not any(RE_HALAMAN.match(ln) for ln in t.split("\n"))]
    dari_scan = _baca_scan(path, tanpa_teks, teks_asing, hasil, log) if tanpa_teks or teks_asing else {}

    # Di bawah kira-kira 180 dpi angka mulai banyak yang salah baca; juru bayar perlu tahu.
    rendah = sorted(h["dpi"] for h in dari_scan.values() if h.get("dpi") and h["dpi"] < DPI_MINIMAL)
    if rendah:
        hasil["catatan_scan"].append(
            f"{len(rendah)} halaman di-scan pada resolusi rendah (sekitar {rendah[0]} dpi). Di bawah "
            f"{DPI_MINIMAL} dpi banyak angka salah baca. Scan ulang pada 200-300 dpi bila bisa.")

    for no_hal, teks in enumerate(teks_hal, 1):
        if no_hal in dari_scan:
            h = dari_scan[no_hal]
            hasil["halaman_scan"] += not h.get("kosong", False)
            hasil["bulan"] = hasil["bulan"] or h["bulan"]
            hasil["anggota"].extend(h["anggota"])
            hasil["galat"].extend(h["galat"])
            if h["lembar"]:
                hasil["lembar"].append(h["lembar"])
            continue
        lines = teks.split("\n")
        if hasil["bulan"] is None:
            m = RE_BULAN.search(lines[0] if lines else "")
            hasil["bulan"] = m.group(1) if m else None
        try:
            mulai = next(i for i, ln in enumerate(lines) if RE_HALAMAN.match(ln)) + 1
        except StopIteration:
            continue
        akhir_idx = [i for i in range(mulai, len(lines)) if RE_AKHIR.match(lines[i])]
        anggota_hal, awal = [], mulai
        for k in akhir_idx:
            try:
                anggota_hal.append(_parse_anggota(lines[awal:k + 1], path.name, no_hal))
            except Exception as e:  # noqa: BLE001
                hasil["galat"].append(f"Hal {no_hal}, baris {awal}-{k}: gagal dibaca ({e})")
            awal = k + 1
        hasil["anggota"].extend(anggota_hal)

        # Baris JUMLAH LEMBAR: 35 angka subtotal sebelum teks 'JUMLAH LEMBAR KE'
        try:
            k_jml = next(i for i in range(awal, len(lines)) if lines[i].startswith("JUMLAH LEMBAR"))
            subtotal = _angka_dari(lines[awal:k_jml], harap=len(KOLOM_ANGKA))
        except (StopIteration, ValueError):
            continue
        if len(subtotal) == len(KOLOM_ANGKA) and anggota_hal:
            sub = dict(zip(KOLOM_ANGKA, subtotal))
            beda = [f"{k}: PDF {sub[k]:,} vs jumlah anggota {sum(a[k] for a in anggota_hal):,}"
                    for k in KOLOM_ANGKA if sub[k] != sum(a[k] for a in anggota_hal)]
            hasil["lembar"].append({"halaman": no_hal, "anggota": len(anggota_hal),
                                    "dibayarkan": sub["dibayarkan"], "cocok": not beda, "beda": beda})
    return hasil


if __name__ == "__main__":
    import sys
    for f in sys.argv[1:]:
        h = baca_dpp(f)
        print(f"{h['file']}: bulan={h['bulan']} anggota={len(h['anggota'])} "
              f"lembar_cocok={sum(l['cocok'] for l in h['lembar'])}/{len(h['lembar'])} galat={len(h['galat'])}")
        for g in h["galat"]:
            print("  GALAT", g)
        for a in h["anggota"]:
            if a["masalah"]:
                print("  MASALAH", a["no_urut"], a["nama"], a["masalah"])
