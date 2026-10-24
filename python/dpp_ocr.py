"""
Pembaca DPP hasil SCAN (halaman PDF tanpa lapisan teks).

Dipanggil dpp_parser.baca_dpp() hanya untuk halaman yang tidak punya lapisan teks; PDF asli
dari aplikasi gaji tetap dibaca lewat jalur teks dan tidak pernah lewat sini.

Cara kerjanya mengikuti tabel DPP, bukan urutan teks:
  1. halaman dirender jadi gambar, diluruskan, lalu garis tabelnya dicari;
  2. garis itu dicocokkan dengan pola kolom DPP (GARIS_V) sehingga letak ke-15 kolom diketahui;
  3. tiap kolom dipotong jadi strip dan dibaca OCR bawaan Windows (ocr_windows.ps1);
  4. tiap angka ditaruh ke kolomnya menurut letak baris di dalam kotak anggota (PETA).

OCR sering melewatkan angka 0 yang berdiri sendiri, jadi sel yang tidak terbaca dianggap 0.
Anggapan itu tidak dipercaya begitu saja: validasi aritmatika per anggota dan pencocokan
JUMLAH LEMBAR yang sama dengan jalur teks tetap dijalankan, sehingga angka yang hilang atau
salah baca muncul sebagai temuan, tidak lolos diam-diam. Yang TIDAK punya pemeriksaan silang
adalah nama, NRP, dan nomor rekening - itu dicatat di rec["ocr"] supaya dilaporkan.
"""
import difflib
import json
import os
import re
import subprocess
import tempfile
import threading
from pathlib import Path

from dpp_parser import KOLOM_ANGKA, PANGKAT, POTONGAN, TUNJANGAN, _validasi_anggota

# ---- Tata letak DPP, diukur pada lebar halaman 4200 piksel (kertas 14 inci, 300 dpi) ----
LEBAR_ACUAN = 4200
# Garis tegak: batas kolom 1|2, 2|3, ... 14|15
GARIS_V = [281, 721.5, 896.5, 1146.5, 1415, 1684, 1952.5, 2212, 2431, 2618.5, 2852.5,
           3099.5, 3302.5, 3537]
# Garis datar kepala tabel: atas, bawah judul PENGHASILAN/POTONGAN, bawah kepala, bawah nomor kolom
GARIS_H_KEPALA = [327.5, 374.5, 577.5, 634]
TINGGI_KOTAK = 234.5         # tinggi kotak satu anggota (= kotak JUMLAH LEMBAR)
JARAK_BARIS = 44             # jarak antarbaris teks di dalam kotak
AWAL_BARIS = 22              # titik tengah baris pertama, dihitung dari garis atas kotak
LEBAR_KOL1 = 105
LEBAR_KOL15 = 370
# kolom DPP -> {baris di dalam kotak: nama angka}
PETA = {
    4: {0: "g_pokok", 1: "t_istri", 2: "t_anak", 4: "g_bruto"},
    5: {0: "t_struktural", 1: "t_fungsional", 2: "t_umum"},
    6: {0: "t_beras", 1: "t_kowan", 2: "t_sandi", 3: "t_babinsa"},
    7: {0: "t_papua", 1: "t_pencil", 2: "t_terluar", 3: "t_terampil"},
    8: {0: "t_lainnya", 1: "tpp", 2: "t_pph", 3: "pembulatan"},
    9: {0: "jml_kotor"},
    10: {0: "p_beras", 1: "p_pensiun", 2: "p_bpjs", 3: "p_tht", 4: "p_bpjs_lain"},
    11: {0: "p_sewa_rumah", 1: "p_utang", 2: "p_pph21"},
    12: {0: "p_pengembalian", 1: "p_tgr", 2: "p_lainnya"},
    13: {0: "jml_potongan"},
    14: {0: "bersih", 1: "lauk_pauk", 3: "dibayarkan"},
}
assert sorted(n for k in PETA.values() for n in k.values()) == sorted(KOLOM_ANGKA)

# Persamaan yang mengikat ke-35 angka; tiap angka ikut sedikitnya satu. (tanda, nama) berjumlah 0.
PERSAMAAN = [
    [(1, "g_pokok"), (1, "t_istri"), (1, "t_anak"), (-1, "g_bruto")],
    [(1, "g_bruto")] + [(1, k) for k in TUNJANGAN] + [(1, "t_pph"), (1, "pembulatan"), (-1, "jml_kotor")],
    [(1, k) for k in POTONGAN] + [(-1, "jml_potongan")],
    [(1, "jml_kotor"), (-1, "jml_potongan"), (-1, "bersih")],
    [(1, "bersih"), (1, "lauk_pauk"), (-1, "dibayarkan")],
    [(1, "t_pph"), (-1, "p_pph21")],
]

AMBANG_GELAP = 150
BANTALAN = (100, 50)         # tepi putih di sekeliling strip, supaya OCR tidak memotong huruf
SKALA_KEDUA = 1.5            # pembesaran strip untuk bacaan kedua kolom nama dan rekening
BULAN = ["JANUARI", "FEBRUARI", "MARET", "APRIL", "MEI", "JUNI", "JULI", "AGUSTUS", "SEPTEMBER",
         "OKTOBER", "NOVEMBER", "DESEMBER"]
_SALAH_BACA = str.maketrans("OoQDlI|!SsBZzGg", "000011115588269")
_HUBUNG = "-\u2010\u2011\u2012\u2013\u2014\u2015\u2212_~"


class GagalOCR(Exception):
    """Kegagalan yang pesannya sudah siap ditampilkan ke juru bayar."""


# --------------------------------------------------------------------------- gambar

def _profil(mask, sumbu, kotak):
    """Rata-rata kegelapan (0..1) tiap baris ('y') atau tiap kolom piksel ('x') di dalam kotak."""
    from PIL import Image
    p = mask.crop(kotak)
    kecil = p.resize((1, p.height) if sumbu == "y" else (p.width, 1), Image.BOX)
    return [v / 255 for v in kecil.tobytes()]


def _garis(profil, awal=0, jendela=5):
    """Letak garis pada profil: puncak jumlah-geser yang menonjol. Jendela membuat garis yang
    sedikit miring (terbagi ke beberapa baris piksel) tetap terhitung utuh."""
    n = len(profil)
    if n < jendela:
        return []
    geser, s = [], sum(profil[:jendela])
    for i in range(n - jendela + 1):
        geser.append(s)
        if i + jendela < n:
            s += profil[i + jendela] - profil[i]
    ambang = max(0.3 * max(geser), 1.2)
    hasil, i = [], 0
    while i < len(geser):
        if geser[i] >= ambang:
            j = i
            while j + 1 < len(geser) and geser[j + 1] >= ambang:
                j += 1
            berat = sum(geser[i:j + 1])
            hasil.append(awal + sum(k * geser[k] for k in range(i, j + 1)) / berat + (jendela - 1) / 2)
            i = j + 1
        else:
            i += 1
    return hasil


def _cocok_pola(temuan, pola, skala, toleransi, minimal):
    """Cari a, b sehingga a*pola + b menimpa garis yang ditemukan. Mengembalikan (a, b) dengan
    kecocokan terbanyak, atau None. Tahan terhadap garis palsu (bayangan tepi scan) maupun
    garis yang tidak terdeteksi."""
    terbaik, n_terbaik, galat_terbaik = None, minimal - 1, 0.0
    for i, d1 in enumerate(temuan):
        for d2 in temuan[i + 1:]:
            for j, t1 in enumerate(pola):
                for t2 in pola[j + 1:]:
                    a = (d2 - d1) / (t2 - t1)
                    if not skala[0] <= a <= skala[1]:
                        continue
                    b = d1 - a * t1
                    n, galat = 0, 0.0
                    for t in pola:
                        dekat = min(abs(a * t + b - d) for d in temuan)
                        if dekat <= toleransi:
                            n, galat = n + 1, galat + dekat
                    if n > n_terbaik or (n == n_terbaik and terbaik and galat < galat_terbaik):
                        terbaik, n_terbaik, galat_terbaik = (a, b), n, galat
    if not terbaik:
        return None
    # haluskan dengan kuadrat terkecil atas semua garis yang cocok
    a, b = terbaik
    pasang = []
    for t in pola:
        d = min(temuan, key=lambda d: abs(a * t + b - d))
        if abs(a * t + b - d) <= toleransi:
            pasang.append((t, d))
    n = len(pasang)
    mt, md = sum(t for t, _ in pasang) / n, sum(d for _, d in pasang) / n
    var = sum((t - mt) ** 2 for t, _ in pasang)
    if var:
        a = sum((t - mt) * (d - md) for t, d in pasang) / var
        b = md - a * mt
    return a, b


def _luruskan(img):
    """Putar gambar sampai garis tabelnya datar. Sudut dicari dengan mencoba: profil baris
    paling tajam saat garis datar tepat sejajar piksel."""
    from PIL import Image
    kecil = img.resize((max(1, img.width // 4), max(1, img.height // 4)), Image.BOX)
    mask = kecil.point(lambda v: 255 if v < AMBANG_GELAP + 20 else 0)

    def skor(sudut):
        r = mask.rotate(sudut, resample=Image.BILINEAR) if sudut else mask
        p = r.resize((1, r.height), Image.BOX).tobytes()
        return sum((p[i] - p[i + 1]) ** 2 for i in range(len(p) - 1))

    terbaik = max((k * 0.25 for k in range(-12, 13)), key=skor)
    terbaik = max((terbaik + k * 0.05 for k in range(-5, 6)), key=skor)
    if abs(terbaik) < 0.03:
        return img, 0.0
    return img.rotate(terbaik, resample=Image.BICUBIC, fillcolor=255), terbaik


def _kenali_tabel(img):
    """Cari letak tabel DPP pada gambar yang sudah lurus.

    Mengembalikan dict: v (14 garis tegak), a (skala), kotak (daftar (atas, bawah) tiap kotak
    anggota/JUMLAH LEMBAR), atas_kepala. Atau None bila pola tabel DPP tidak ditemukan.
    """
    W, H = img.size
    mask = img.point(lambda v: 255 if v < AMBANG_GELAP else 0)
    datar = _garis(_profil(mask, "y", (int(W * 0.12), 0, int(W * 0.88), H)))
    if len(datar) < 4:
        return None
    perkiraan = W / LEBAR_ACUAN
    kepala = _cocok_pola(datar, GARIS_H_KEPALA, (perkiraan * 0.6, perkiraan * 1.5), 5, 4)
    if not kepala:
        return None
    ah, bh = kepala
    y_kepala = [ah * t + bh for t in GARIS_H_KEPALA]
    tegak = _garis(_profil(mask, "x", (0, int(y_kepala[0]), W, int(y_kepala[3]))))
    pas = _cocok_pola(tegak, GARIS_V, (ah * 0.93, ah * 1.07), 6, 11)
    if not pas:
        return None
    a, b = pas
    v = [a * t + b for t in GARIS_V]

    tinggi = TINGGI_KOTAK * ah
    bawah_kepala = min(datar, key=lambda d: abs(d - y_kepala[3]))
    batas = [bawah_kepala] + [d for d in datar if d > bawah_kepala + tinggi * 0.4]
    kotak = []
    for y0, y1 in zip(batas, batas[1:]):
        n = round((y1 - y0) / tinggi)
        if n < 1 or abs((y1 - y0) - n * tinggi) > 0.06 * tinggi:
            break                      # blok JUMLAH penutup atau tanda tangan: bukan kotak anggota
        kotak += [(y0 + k * (y1 - y0) / n, y0 + (k + 1) * (y1 - y0) / n) for k in range(n)]
    return {"v": v, "a": a, "ah": ah, "kotak": kotak, "atas_kepala": y_kepala[0]}


def _siapkan_halaman(img_asal, folder, awalan):
    """Luruskan, kenali tabel, potong strip per kolom. Mengembalikan (geometri, [jalur strip])
    atau melempar GagalOCR."""
    from PIL import Image
    geo = None
    for putar in ((0, 180) if img_asal.width >= img_asal.height else (90, 270)):
        img = img_asal.rotate(putar, expand=True) if putar else img_asal
        if img.width != LEBAR_ACUAN:
            img = img.resize((LEBAR_ACUAN, round(img.height * LEBAR_ACUAN / img.width)), Image.LANCZOS)
        img, sudut = _luruskan(img)
        geo = _kenali_tabel(img)
        if geo:
            geo["sudut"] = sudut
            break
    if not geo:
        raise GagalOCR("tabel DPP tidak dikenali pada hasil scan (terlalu miring, buram, terpotong, "
                       "atau bukan lembar DPP gaji induk)")
    v, a = geo["v"], geo["a"]
    strip = {"kepala": (0, 0, img.width, max(10, int(geo["atas_kepala"]) - 3))}
    if geo["kotak"]:
        y0, y1 = int(geo["kotak"][0][0]) + 3, int(geo["kotak"][-1][1]) - 2
        tepi = [v[0] - LEBAR_KOL1 * a] + v + [v[-1] + LEBAR_KOL15 * a]
        for kol in [1, 2] + list(PETA) + [15]:
            strip[kol] = (int(tepi[kol - 1] + 4.5), y0, int(tepi[kol] - 3.5), y1)
    geo["strip"] = strip
    jalur = []
    for kunci, kt in strip.items():
        kt = (max(0, kt[0]), max(0, kt[1]), min(img.width, kt[2]), min(img.height, kt[3]))
        bantal = (0, 0) if kunci == "kepala" else BANTALAN
        pot = img.crop(kt)
        kanvas = Image.new("L", (pot.width + 2 * bantal[0], pot.height + 2 * bantal[1]), 255)
        kanvas.paste(pot, bantal)
        p = Path(folder) / f"{awalan}_{kunci}.png"
        kanvas.save(p)
        jalur.append((kunci, p))
        if kunci in (2, 15):                   # bacaan kedua untuk nama, NRP, rekening
            p = Path(folder) / f"{awalan}_{kunci}b.png"
            kanvas.resize((round(kanvas.width * SKALA_KEDUA), round(kanvas.height * SKALA_KEDUA)),
                          Image.LANCZOS).save(p)
            jalur.append((f"{kunci}b", p))
    return geo, jalur


# --------------------------------------------------------------------------- OCR

def _jalankan_ocr(daftar_gambar, folder, maju=None):
    """Baca semua gambar dengan OCR Windows. Mengembalikan {jalur: [kata, ...]}."""
    if os.name != "nt":
        raise GagalOCR("pembacaan hasil scan hanya tersedia di Windows 10/11")
    skrip = Path(__file__).with_name("ocr_windows.ps1")
    if not skrip.exists():
        raise GagalOCR(f"berkas {skrip.name} tidak ada di folder program")
    daftar = Path(folder) / "daftar.txt"
    daftar.write_text("\n".join(str(p) for p in daftar_gambar), encoding="utf-8")
    # Skrip dijalankan sebagai blok perintah, bukan berkas, supaya kebijakan eksekusi skrip
    # di komputer kantor tidak menghalanginya.
    kutip = lambda p: "'" + str(p).replace("'", "''") + "'"  # noqa: E731
    perintah = (f"& ([scriptblock]::Create((Get-Content -Raw -LiteralPath {kutip(skrip)}))) "
                f"-Daftar {kutip(daftar)}")
    try:
        pr = subprocess.Popen(
            ["powershell", "-NoProfile", "-NonInteractive", "-Command", perintah],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, stdin=subprocess.DEVNULL,
            text=True, encoding="utf-8", errors="replace",
            creationflags=getattr(subprocess, "CREATE_NO_WINDOW", 0))
    except OSError as e:
        raise GagalOCR(f"PowerShell tidak bisa dijalankan ({e})") from e
    # OCR yang macet tidak boleh menggantung aplikasi selamanya.
    batas = threading.Timer(120 + 20 * len(daftar_gambar), pr.kill)
    batas.start()
    hasil = {}
    try:
        for baris in pr.stdout:
            baris = baris.strip().lstrip("\ufeff")
            if not baris.startswith("{"):
                continue
            d = json.loads(baris)
            kata = d.get("kata") or []
            hasil[d["gambar"]] = [kata] if isinstance(kata, dict) else kata
            if maju:
                maju(len(hasil), len(daftar_gambar))
        galat = pr.stderr.read()
        kode = pr.wait()
    finally:
        batas.cancel()
    if kode != 0:
        if "TANPA_MESIN_OCR" in galat:
            raise GagalOCR("Windows di komputer ini belum punya paket bahasa OCR. Buka Settings > "
                           "Time & language > Language, tambahkan English (United States) berikut "
                           "fitur 'Optical character recognition', lalu coba lagi")
        raise GagalOCR("OCR Windows gagal: " + (galat.strip().splitlines() or ["tanpa pesan"])[-1][:300])
    return hasil


# --------------------------------------------------------------------------- tafsir

def _ke_angka(teks):
    """Angka rupiah dari teks hasil OCR; None bila tidak bisa ditafsirkan."""
    s = teks.strip().replace(",", ".").replace(" ", "")
    negatif = False
    if s.startswith("(") and s.endswith(")"):
        negatif, s = True, s[1:-1]
    elif len(s) > 1 and s[0] in _HUBUNG:
        negatif, s = True, s[1:]
    elif len(s) > 1 and s[-1] in _HUBUNG:
        negatif, s = True, s[:-1]
    s = s.translate(_SALAH_BACA).strip(".:;'`")
    # Titik ribuan boleh hilang seluruhnya, tetapi kalau ada harus tepat tiga-tiga: "2.703.11"
    # berarti ada digit yang tidak terbaca, dan itu lebih baik dihitung ulang daripada dipercaya.
    if not re.fullmatch(r"\d+|\d{1,3}(\.\d{3})+", s):
        return None
    n = int(s.replace(".", ""))
    return -n if negatif else n


def _angka_identitas(teks, minimal, titik=False):
    """Deret digit (NRP, rekening) dari teks OCR, atau '' bila bukan deret digit.
    titik=True mempertahankan titik pemisah, seperti pada nomor rekening 0122.01.091502.50.0."""
    asli = re.sub(r"[\s.\-]", "", teks)
    s = asli.translate(_SALAH_BACA)
    if len(s) >= minimal and s.isdigit() and sum(c.isdigit() for c in asli) >= 0.7 * len(asli):
        if titik and "." in teks.strip(" ."):
            return re.sub(r"[^\d.]", "", re.sub(r"[\s\-]", "", teks).translate(_SALAH_BACA)).strip(".")
        return s
    return ""


def _rekening(sel15, i):
    """Nomor rekening kotak ke-i: deret digit pertama di kolom 15."""
    return next((_angka_identitas(t, 9, titik=True) for b in sorted(b for (k, b) in sel15 if k == i)
                 for t in sel15[(i, b)] if _angka_identitas(t, 9)), "")


def _ringkas(teks):
    """Bentuk pembanding: huruf dan angka saja, kapital semua."""
    return re.sub(r"[^A-Z0-9]", "", str(teks).upper())


def _isi_sel(kata_strip, geo, kol, skala=1.0):
    """Kelompokkan kata hasil OCR satu strip menjadi {(kotak, baris): 'teks'}.
    skala = pembesaran gambar strip saat dibaca (bacaan kedua memakai strip yang diperbesar)."""
    x0, y0 = geo["strip"][kol][0], geo["strip"][kol][1]
    jarak, awal = JARAK_BARIS * geo["ah"], AWAL_BARIS * geo["ah"]
    sel = {}
    for k in kata_strip:
        yc = (k["y"] + k["h"] / 2) / skala - BANTALAN[1] + y0
        for i, (atas, bawah) in enumerate(geo["kotak"]):
            if atas <= yc < bawah:
                baris = round((yc - atas - awal) / jarak)
                sel.setdefault((i, baris), []).append((k["x"] / skala - BANTALAN[0] + x0, k["t"]))
                break
    return {kunci: [t for _, t in sorted(isi)] for kunci, isi in sel.items()}


def _kotak_sel(geo, kol, i, baris):
    """Letak sel (kotak i, baris ke-`baris`) di dalam gambar strip kolom `kol`."""
    sx0, sy0, sx1, _ = geo["strip"][kol]
    ah = geo["ah"]
    tengah = geo["kotak"][i][0] + (AWAL_BARIS + baris * JARAK_BARIS) * ah - sy0 + BANTALAN[1]
    return (BANTALAN[0], int(tengah - 17 * ah), BANTALAN[0] + sx1 - sx0, int(tengah + 19 * ah))


def _bentuk_nol(mask, bb, ah):
    """True bila tinta di dalam kotak bb berbentuk satu angka 0: seukuran satu digit, bertinta
    di keempat sisinya, dan berlubang di tengah. Digit lain selalu bertinta di tengah atau
    kosong di salah satu sisi."""
    from PIL import Image
    w, h = bb[2] - bb[0], bb[3] - bb[1]
    if not (8 * ah <= w <= 22 * ah and 18 * ah <= h <= 32 * ah):
        return False
    g = mask.crop(bb)

    def isi(fx0, fy0, fx1, fy1):
        x0, y0 = int(w * fx0), int(h * fy0)
        k = g.crop((x0, y0, max(x0 + 1, round(w * fx1)), max(y0 + 1, round(h * fy1))))
        return k.resize((1, 1), Image.BOX).tobytes()[0] / 255

    return (isi(.38, .3, .62, .7) < 0.08 and isi(.25, 0, .75, .18) > .35 and isi(.25, .82, .75, 1) > .35
            and isi(0, .3, .3, .7) > .35 and isi(.7, .3, 1, .7) > .35)


def _jenis_kotak(geo):
    """Golongkan tiap kotak dari tulisan di kolom 2: 'lembar' (baris JUMLAH LEMBAR), 'jumlah'
    (blok JUMLAH penutup daftar), atau 'anggota'."""
    jenis = []
    for i in range(len(geo["kotak"])):
        semua = " ".join(" ".join(t) for (k, _), t in geo["sel"][2].items() if k == i)
        kata = [w for w in re.split(r"[\s:.]+", semua) if w and not w.isdigit()]
        if any(_mirip(w, "LEMBAR") >= 0.75 for w in kata):
            jenis.append("lembar")
        elif kata and all(_mirip(w, "JUMLAH") >= 0.7 or w.upper() == "KE" for w in kata):
            jenis.append("jumlah")
        else:
            jenis.append("anggota")
    return jenis


def _periksa_sel_kosong(geo, jalur):
    """Periksa sel yang seharusnya berisi tetapi tidak menghasilkan kata apa pun dari OCR.

    OCR nyaris selalu melewatkan angka 0 yang berdiri sendiri. Sel seperti itu dikenali dari
    bentuk tintanya (pasti_nol). Sel bertinta yang bukan angka 0 dikumpulkan untuk dibaca
    ulang (calon): [((kolom, kotak, baris), gambar potongan), ...].
    """
    from PIL import Image
    ah, sel = geo["ah"], geo["sel"]
    aktif = [i for i in range(len(geo["kotak"])) if geo["jenis"][i] != "jumlah"
             and sum(1 for kol in PETA for (k, _) in sel[kol] if k == i) >= 3]
    pasti, calon = set(), []
    for kol in [1, 2, 15] + list(PETA):
        harap = PETA.get(kol) or ((0,) if kol in (1, 15) else range(5))
        img = mask = None
        for i in aktif:
            if kol not in PETA and geo["jenis"][i] != "anggota":
                continue
            for baris in harap:
                if sel[kol].get((i, baris)):
                    continue
                if img is None:
                    img = Image.open(jalur[kol])
                    mask = img.point(lambda v: 255 if v < AMBANG_GELAP else 0)
                kt = _kotak_sel(geo, kol, i, baris)
                bb = mask.crop(kt).getbbox()
                if not bb:
                    continue
                bb = (kt[0] + bb[0], kt[1] + bb[1], kt[0] + bb[2], kt[1] + bb[3])
                if kol in PETA and _bentuk_nol(mask, bb, ah):
                    pasti.add((kol, i, baris))
                elif kol in PETA or bb[2] - bb[0] >= 12 * ah:
                    calon.append(((kol, i, baris), img.crop((bb[0] - 6, bb[1] - 6, bb[2] + 6, bb[3] + 6))))
    return pasti, calon


def _mutu_baris(kata):
    """Nilai untuk memilih di antara beberapa bacaan satu baris: yang berbentuk 'NRP KORPS'
    menang, selebihnya yang paling banyak hurufnya."""
    korps = re.sub(r"[^A-Z]", "", kata[-1].upper().translate(str.maketrans("!1|0", "IIIO")))
    nrp = len(kata) >= 2 and 2 <= len(korps) <= 4 and bool(_angka_identitas("".join(kata[:-1]), 5))
    return (nrp, sum(c.isalnum() for w in kata for c in w))


def _huruf(ukuran):
    from PIL import ImageFont
    try:
        return ImageFont.truetype("arial.ttf", ukuran)
    except OSError:
        return ImageFont.load_default(ukuran)


def _susun_ulang(calon, jalur):
    """Jajarkan potongan sel yang belum terbaca menjadi baris-baris kalimat untuk dibaca ulang.

    OCR Windows tidak mau membaca karakter yang berdiri sendiri, tetapi membacanya dengan
    baik bila diapit kata biasa. Jadi tiap potongan diselipkan di antara kata JANGKAR, dan
    hasilnya dipetakan kembali ke sel lewat letaknya. Baris teks yang rapat (deret angka 1)
    direnggangkan mendatar. Mengembalikan [((kunci, varian), x0, y0, x1, y1), ...].
    """
    from PIL import Image, ImageDraw
    jangkar, jeda, tepi, lebar_maks = "nilai", 40, 6, 2400
    baris, kini, x = [], [], 60
    for kunci, pot in calon:
        # latar abu-abu hasil scan diputihkan, supaya potongan tidak tampak sebagai kotak gambar
        pot = pot.point(lambda v: 255 if v >= 170 else v * 255 // 170)
        teks = kunci[0] not in PETA and kunci[0] != 1
        if teks:
            # Baris nama/NRP/rekening: sendiri, direnggangkan mendatar. Mana perenggangan yang
            # terbaca tidak bisa ditebak, jadi dicoba dua dan yang terbaik dipilih pemanggil.
            for varian, (sx, sy) in enumerate(((1.6, 1), (2.4, 2))):
                baris.append((34 * sy, [((kunci, varian), pot.resize(
                    (round(pot.width * sx), pot.height * sy), Image.LANCZOS))]))
            continue
        if kini and x + pot.width > lebar_maks:
            baris.append((34, kini))
            kini, x = [], 60
        kini.append(((kunci, 0), pot))
        x += pot.width + 2 * jeda + 90
    if kini:
        baris.append((34, kini))

    tempel, peta, y, lebar = [], [], 40, 0
    for ukuran, isi in baris:
        font = _huruf(ukuran)
        skala = ukuran // 34
        lj = round(font.getlength(jangkar))
        dasar = y + font.getbbox(jangkar)[3]
        x = 60
        for kunci, pot in isi:
            tempel.append((jangkar, font, x, y))
            x += lj + jeda * skala
            tempel.append((pot, None, x, dasar - pot.height + tepi * skala))
            peta.append((kunci, x - jeda * skala // 2, y - 10, x + pot.width + jeda * skala // 2,
                         y + ukuran + 30))
            x += pot.width + jeda * skala
        tempel.append((jangkar, font, x, y))
        lebar = max(lebar, x + lj + 60)
        y += ukuran * 3
    kanvas = Image.new("L", (lebar, y + 20), 255)
    gambar = ImageDraw.Draw(kanvas)
    for apa, font, x, yy in tempel:
        if font:
            gambar.text((x, yy), apa, font=font, fill=0)
        else:
            kanvas.paste(apa, (x, yy))
    kanvas.save(jalur)
    return peta


def _lengkapi(nilai, rusak, lemah=()):
    """Isi angka yang tidak terbaca memakai persamaan DPP.

    nilai : {nama: int | None}; None = sel tidak terbaca sama sekali (kemungkinan besar 0).
    rusak : nama-nama yang selnya berisi teks tetapi tidak bisa ditafsirkan sebagai angka.
    lemah : nama-nama yang angkanya baru terbaca pada baca-ulang (_susun_ulang). Bacaan itu
            lebih sering salah satu digit daripada bacaan strip, jadi bila ia satu-satunya
            penyebab persamaan tidak cocok, hasil hitungan yang dipakai.
    Mengembalikan (catatan, tidak_terbaca, n_dianggap_nol). Yang dihitung dari persamaan
    dicatat; yang tetap tidak diketahui diisi 0 dan dilaporkan supaya validasi menandainya.
    """
    kosong = {k for k in KOLOM_ANGKA if nilai.get(k) is None and k not in rusak}
    n_kosong = len(kosong)
    for k in kosong:
        nilai[k] = 0
    rusak, catatan = set(rusak), []
    maju = True
    while rusak and maju:
        maju = False
        for pers in PERSAMAAN:
            tak = [(t, k) for t, k in pers if k in rusak]
            if len(tak) == 1:
                t, k = tak[0]
                nilai[k] = -t * sum(tt * nilai[kk] for tt, kk in pers if kk != k)
                rusak.discard(k)
                catatan.append(f"{k} tidak terbaca jelas, dihitung dari angka lain = {nilai[k]:,}")
                maju = True
    for k in rusak:
        nilai[k] = 0
    sisa = lambda pers: sum(t * nilai[k] for t, k in pers)  # noqa: E731
    # Satu-satunya angka tak pasti (sel kosong atau bacaan lemah) di persamaan yang tidak cocok:
    # nilainya pasti selisihnya. Boleh berantai (G.BRUTO dari JUMLAH KOTOR, lalu G.POKOK dari
    # G.BRUTO), tetapi hasilnya hanya dipakai bila sesudahnya SEMUA persamaan cocok; kalau
    # tidak, semuanya dikembalikan seperti semula dan anggota itu ditandai oleh validasi.
    tak_pasti = kosong | {k for k in lemah if k not in rusak}
    semula, diisi, maju = dict(nilai), [], True
    while maju:
        maju = False
        for pers in PERSAMAAN:
            calon = [(t, k) for t, k in pers if k in tak_pasti]
            if sisa(pers) == 0 or len(calon) != 1:
                continue
            t, k = calon[0]
            nilai[k] = -t * sum(tt * nilai[kk] for tt, kk in pers if kk != k)
            tak_pasti.discard(k)
            diisi.append(k)
            maju = True
    if diisi and all(sisa(p) == 0 for p in PERSAMAAN):
        catatan += [f"{k} terbaca {semula[k]:,} tetapi tidak cocok dengan hitungan, dipakai hasil "
                    f"hitungan = {nilai[k]:,}" if k in lemah else
                    f"{k} tidak terbaca, dihitung dari angka lain = {nilai[k]:,}" for k in diisi]
    else:
        for k in diisi:
            nilai[k] = semula[k]
    return catatan, sorted(rusak), n_kosong


def _angka_kotak(sel_kolom, i, pasti_nol=(), baca_ulang=()):
    """35 angka satu kotak. Mengembalikan (nilai, rusak, lemah, n_terbaca).
    pasti_nol  = sel (kolom, kotak, baris) yang tidak terbaca OCR tetapi bentuknya angka 0.
    baca_ulang = sel yang isinya berasal dari baca-ulang; angkanya masuk daftar `lemah`."""
    nilai, rusak, lemah, terbaca = {}, set(), set(), 0
    for kol, peta in PETA.items():
        for baris, nama in peta.items():
            teks = sel_kolom[kol].get((i, baris))
            if (kol, i, baris) in baca_ulang:
                lemah.add(nama)
            if not teks and (kol, i, baris) in pasti_nol:
                nilai[nama] = 0
                terbaca += 1
                continue
            if not teks:
                nilai[nama] = None
                continue
            # Kata-kata satu sel disambung apa adanya; kalau hasilnya bukan angka yang sah, sel
            # dianggap rusak dan dihitung dari persamaan, tidak dipaksa jadi angka.
            n = _ke_angka("".join(teks))
            if n is None and all(c in _HUBUNG + "." for c in "".join(teks)):
                nilai[nama] = None             # garis pemisah yang nyasar ke baris angka
            elif n is None:
                nilai[nama] = None
                rusak.add(nama)
            else:
                nilai[nama] = n
                terbaca += 1
    return nilai, rusak, lemah, terbaca


def _mirip(a, b):
    return difflib.SequenceMatcher(None, a.upper(), b.upper()).ratio()


def _rapikan_kata(w):
    """Buang salah baca khas mesin OCR pada huruf kapital: U terbaca 'I-J', L terbaca 'I-',
    dan tanda hubung yang nyelip di tengah kata."""
    if "-" in w and w.replace("-", "").isalpha():
        w = w.replace("I-J", "U")
        if w.startswith("I-"):
            w = "L" + w[2:]
        w = w.replace("-", "")
    w = re.sub(r"(?<![A-Za-z])s\.(?=[A-Za-z])", "S.", w)      # gelar: s.Pd. -> S.Pd.
    # Kata berhuruf kapital yang kemasukan huruf kecil/angka mirip: pRATIWl -> PRATIWI
    huruf = [c for c in w if c.isalpha()]
    if len(huruf) >= 3 and len(huruf) >= len(w) - 1 and not w.isupper() \
            and sum(c.isupper() for c in huruf) >= 0.6 * len(huruf):
        w = w.translate(str.maketrans("l|!1", "IIII")).translate(str.maketrans("05", "OS")).upper()
    return w


def _identitas(baris_teks, rec):
    """Isi nama, NRP, korps, tanggal, pangkat, jabatan, NPWP dari baris-baris kolom 2."""
    baris = [" ".join(_rapikan_kata(w) for w in baris_teks[k]) for k in sorted(baris_teks) if 0 <= k <= 5]
    i_nrp = None
    for i, ln in enumerate(baris):
        tok = ln.split()
        korps = re.sub(r"[^A-Z]", "", tok[-1].upper().translate(str.maketrans("!1|0", "IIIO"))) if tok else ""
        if len(tok) >= 2 and 2 <= len(korps) <= 4 and not any(c.isdigit() for c in tok[-1][:2]) \
                and _angka_identitas("".join(tok[:-1]), 5):
            i_nrp = i
            rec["nrp"], rec["korps"] = _angka_identitas("".join(tok[:-1]), 5), korps
            break
    if i_nrp is None:                          # korps tidak terbaca: NRP = baris digit panjang
        for i, ln in enumerate(baris[:3]):
            if i and "-" not in ln and _angka_identitas(ln, 10):
                i_nrp, rec["nrp"], rec["korps"] = i, _angka_identitas(ln, 10), ""
                break
    if i_nrp is None:
        # Baris NRP tidak terbaca sama sekali: nama = semua baris sebelum baris tanggal.
        rec["nrp"], rec["korps"] = "", ""
        i_tgl = next((i for i, ln in enumerate(baris) if i and re.match(r"^\d{1,2}\s*-\s*\d", ln)), 1)
        rec["nama"] = " ".join(baris[:i_tgl])
        sisa = baris[i_tgl:]
    else:
        rec["nama"] = " ".join(baris[:i_nrp])
        sisa = baris[i_nrp + 1:]
    rec["tgl_lahir"], rec["tmt_jab"] = "", ""
    if sisa and re.match(r"^[\dOoIl]{1,2}\s*-", sisa[0]):
        tgl = sisa.pop(0).split()
        rec["tgl_lahir"] = tgl[0]
        tmt = tgl[1] if len(tgl) > 1 else ""
        rec["tmt_jab"] = "" if tmt.replace("-", "").strip() == "" else tmt
    teks, nomor = [], []
    for ln in sisa:
        (nomor if _angka_identitas(ln, 9) else teks).append(ln)
    rec["npwp"] = _angka_identitas(nomor[-1], 9) if nomor else ""
    kata = " ".join(teks).split()
    if kata:
        coba = re.sub(r"[^A-Z]", "", kata[0].upper().translate(str.maketrans("015", "OIS")))
        dekat = coba if coba in PANGKAT else next(iter(
            difflib.get_close_matches(coba.replace("IJ", "U"), sorted(PANGKAT), 1, 0.7)), None)
        if dekat:
            ambil = 2 if len(kata) > 1 and kata[1].upper() in ("TN", "TNI") else 1
            rec["pangkat"] = " ".join([dekat] + (["TNI"] if ambil == 2 else []))
            rec["jabatan"] = " ".join(kata[ambil:])
            return
    rec["pangkat"], rec["jabatan"] = "", " ".join(kata)


def _bulan_dari(kata):
    """'OKTOBER 2026' dari kata-kata bagian kepala halaman, atau None."""
    teks = [k["t"] for k in sorted(kata, key=lambda k: (round(k["y"] / 25), k["x"]))]
    for i, t in enumerate(teks):
        if _mirip(t, "BULAN") < 0.8:
            continue
        for j in range(i + 1, min(i + 3, len(teks) - 1)):
            nama = difflib.get_close_matches(teks[j].upper(), BULAN, 1, 0.7)
            tahun = teks[j + 1].translate(_SALAH_BACA)
            if nama and re.fullmatch(r"20\d\d", tahun):
                return f"{nama[0]} {tahun}"
    return None


def _tafsir_halaman(geo, sumber, no_hal):
    """Susun anggota + cek JUMLAH LEMBAR satu halaman dari hasil OCR strip-stripnya."""
    out = {"anggota": [], "lembar": None, "galat": [], "bulan": _bulan_dari(geo["kepala"]),
           "dpi": geo.get("dpi")}
    if not geo["kotak"]:
        return out
    sel, pasti_nol = geo["sel"], geo["pasti_nol"]
    for i in range(len(geo["kotak"])):
        teks2 = {b: t for (k, b), t in sel[2].items() if k == i}
        semua = " ".join(" ".join(t) for t in teks2.values())
        nilai, rusak, lemah, terbaca = _angka_kotak(sel, i, pasti_nol, geo["baca_ulang"])
        if geo["jenis"][i] == "lembar":
            _lengkapi(nilai, rusak, lemah)
            ang = out["anggota"]
            if ang:
                beda = [f"{k}: PDF {nilai[k]:,} vs jumlah anggota {sum(x[k] for x in ang):,}"
                        for k in KOLOM_ANGKA if nilai[k] != sum(x[k] for x in ang)]
                out["lembar"] = {"halaman": no_hal, "anggota": len(ang), "ocr": True,
                                 "dibayarkan": nilai["dibayarkan"], "cocok": not beda, "beda": beda}
            break
        if geo["jenis"][i] == "jumlah":
            continue                           # blok JUMLAH penutup daftar, bukan anggota
        if terbaca < 5:
            if semua.strip():
                out["galat"].append(f"Hal {no_hal}, kotak ke-{i + 1}: angka tidak terbaca dari hasil scan")
            continue
        rec = {"file": sumber, "halaman": no_hal, "masalah": [],
               "jab_esln": "", "sts_kawin": "", "tmt_kgb": "", "mkg": ""}
        catatan, tak_terbaca, n_kosong = _lengkapi(nilai, rusak, lemah)
        rec.update({k: nilai[k] for k in KOLOM_ANGKA})
        no = next((_ke_angka(t) for b in sorted(b for (k, b) in sel[1] if k == i)
                   for t in sel[1][(i, b)] if _ke_angka(t) is not None), None)
        rec["no_urut"], rec["_kotak"] = (abs(no) if no is not None else 0), i
        _identitas(teks2, rec)
        rec["rekening"] = _rekening(sel[15], i)
        # Nama, NRP, pangkat, dan rekening tidak punya hitungan pembanding. Sebagai gantinya
        # kolomnya dibaca dua kali pada ukuran gambar berbeda; yang tidak sepakat dicatat.
        kedua = {}
        _identitas({b: t for (k, b), t in geo["sel_kedua"][2].items() if k == i}, kedua)
        kedua["rekening"] = _rekening(geo["sel_kedua"][15], i)
        ragu = [k for k in ("nama", "nrp", "pangkat", "rekening") if _ringkas(rec[k]) != _ringkas(kedua[k])]
        if not rec["nrp"] and kedua["nrp"]:    # baris NRP hanya terbaca di bacaan kedua
            rec.update({k: kedua[k] for k in ("nama", "nrp", "korps", "tgl_lahir", "tmt_jab",
                                              "pangkat", "jabatan", "npwp")})
        for k in ("pangkat", "rekening"):
            rec[k] = rec[k] or kedua[k]
        if not (rec["nrp"] or rec["tgl_lahir"] or rec["pangkat"]) or not (rec["nama"] or rec["nrp"]):
            out["galat"].append(f"Hal {no_hal}, kotak ke-{i + 1}: nama dan NRP tidak terbaca dari hasil scan")
            continue
        _validasi_anggota(rec)
        for k in tak_terbaca:
            rec["masalah"].append(f"{k}: angka di hasil scan tidak terbaca")
        rec["ocr"] = {"dihitung": catatan, "dianggap_nol": n_kosong, "ragu": ragu}
        out["anggota"].append(rec)
    # Nomor urut yang tidak terbaca diturunkan dari tetangganya di lembar yang sama.
    kenal = [(r["_kotak"], r["no_urut"]) for r in out["anggota"] if r["no_urut"]]
    for r in out["anggota"]:
        letak = r.pop("_kotak")
        if not r["no_urut"] and kenal:
            k, no = min(kenal, key=lambda x: abs(x[0] - letak))
            r["no_urut"] = max(0, no + letak - k)
    if out["anggota"] and out["lembar"] is None:
        out["galat"].append(f"Hal {no_hal}: baris JUMLAH LEMBAR tidak terbaca dari hasil scan, jadi "
                            f"{len(out['anggota'])} anggota di lembar ini tidak punya cek subtotal")
    return out


# --------------------------------------------------------------------------- pintu masuk

def _piksel_gambar(hal):
    """Sisi panjang (piksel) gambar terbesar di halaman - pada PDF hasil scan itulah scan-nya.
    None bila halaman tidak berisi gambar atau ukurannya tidak bisa dibaca."""
    try:
        import pypdfium2.raw as mentah
        terbesar = 0
        for obj in hal.get_objects(filter=[mentah.FPDF_PAGEOBJ_IMAGE], max_depth=2):
            ukuran = (getattr(obj, "get_px_size", None) or obj.get_size)()
            terbesar = max(terbesar, *ukuran)
        return terbesar or None
    except Exception:  # noqa: BLE001 - hanya untuk peringatan mutu, tidak boleh menggagalkan baca
        return None

def baca_halaman_scan(path, halaman, log=None):
    """Baca halaman-halaman tanpa lapisan teks dari satu PDF.

    halaman : daftar nomor halaman (mulai 1).
    Mengembalikan {no_hal: {"anggota": [...], "lembar": dict|None, "galat": [...], "bulan": ...}}.
    Kegagalan menyeluruh (komponen tidak ada, OCR tidak tersedia) dilempar sebagai GagalOCR.
    """
    try:
        import pypdfium2 as pdfium
        from PIL import Image  # noqa: F401
    except ImportError as e:
        raise GagalOCR(f"komponen pembaca hasil scan belum terpasang ({e.name}). Jalankan "
                       "1_PASANG_SEKALI.bat (atau MULAI.bat) sekali lagi") from e
    path = Path(path)
    log = log or (lambda *_: None)
    hasil, siap = {}, {}
    with tempfile.TemporaryDirectory(prefix="dpp_ocr_") as folder:
        pdf = pdfium.PdfDocument(str(path))
        try:
            for no in halaman:
                hal = pdf[no - 1]
                lebar, tinggi = hal.get_size()
                img = hal.render(scale=LEBAR_ACUAN / max(lebar, tinggi)).to_pil().convert("L")
                if min(img.resize((64, 64), Image.BOX).tobytes()) > 250:
                    hasil[no] = {"anggota": [], "lembar": None, "bulan": None, "galat": [], "kosong": True}
                    continue                   # halaman kosong
                try:
                    siap[no] = _siapkan_halaman(img, folder, f"h{no:04d}")
                    px = _piksel_gambar(hal)
                    if px:                     # resolusi scan yang sebenarnya, diukur pada tabelnya
                        siap[no][0]["dpi"] = round(300 * siap[no][0]["a"] * px / LEBAR_ACUAN)
                except GagalOCR as e:
                    hasil[no] = {"anggota": [], "lembar": None, "bulan": None,
                                 "galat": [f"Hal {no}: {e}."]}
        finally:
            pdf.close()
        if siap:
            gambar = [p for _, jalur in siap.values() for _, p in jalur]
            langkah = max(1, len(gambar) // 10)
            terbaca = _jalankan_ocr(
                gambar, folder,
                lambda n, total: log(f"      {path.name}: OCR {100 * n // total}% ...")
                if n % langkah == 0 and n < total else None)
            ulang = []
            for no, (geo, jalur) in siap.items():
                kata = {kunci: terbaca.get(str(p), []) for kunci, p in jalur}
                geo["kepala"] = kata.get("kepala", [])
                if not geo["kotak"]:
                    continue                   # hanya kepala tabel/blok JUMLAH: tidak ada anggota
                geo["sel"] = {kol: _isi_sel(kata.get(kol, []), geo, kol) for kol in [1, 2, 15] + list(PETA)}
                geo["sel_kedua"] = {kol: _isi_sel(kata.get(f"{kol}b", []), geo, kol, SKALA_KEDUA)
                                    for kol in (2, 15)}
                geo["jenis"] = _jenis_kotak(geo)
                geo["baca_ulang"] = set()
                geo["pasti_nol"], calon = _periksa_sel_kosong(geo, dict(jalur))
                if calon:
                    p = Path(folder) / f"h{no:04d}_ulang.png"
                    ulang.append((geo, p, _susun_ulang(calon, p)))
            if ulang:
                log(f"      {path.name}: membaca ulang sel yang terlewat ...")
                terbaca = _jalankan_ocr([p for _, p, _ in ulang], folder)
                for geo, p, peta in ulang:
                    dapat = {}
                    for k in sorted(terbaca.get(str(p), []), key=lambda k: k["x"]):
                        if _mirip(k["t"], "nilai") >= 0.6:
                            continue           # kata jangkar
                        xc, yc = k["x"] + k["w"] / 2, k["y"] + k["h"] / 2
                        for (kunci, varian), x0, y0, x1, y1 in peta:
                            if x0 <= xc < x1 and y0 <= yc < y1:
                                dapat.setdefault(kunci, {}).setdefault(varian, []).append(k["t"])
                                break
                    for (kol, i, baris), varian in dapat.items():
                        geo["sel"][kol][(i, baris)] = max(varian.values(), key=_mutu_baris)
                        geo["baca_ulang"].add((kol, i, baris))
            for no, (geo, _) in siap.items():
                hasil[no] = _tafsir_halaman(geo, path.name, no)
    return hasil
