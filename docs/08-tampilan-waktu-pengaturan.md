# Tampilan, waktu, dan pengaturan kop slip

## Logo dan palet warna

- Lambang instansi: `public/img/logo-instansi.png` (komponen `partials/logo.blade.php`), dipakai di sidebar, halaman masuk, dan kop slip.
  Bila berkas tidak ada, tampil monogram "SG" (cadangan).
- Palet diambil dari lambang (`tailwind.config.js`): hijau tua daun (`side` `#12372A`, `brand` `#1B6B3F`, `brand-dark` `#14532D`),
  emas bola (`gold` `#E8B923`), merah pita (`alert` `#A61E1E`). Tombol utama, tab aktif, dan langkah wizard memakai `brand`.
  Kontras teks diukur (WCAG, syarat 4,5:1): putih di `brand` 6,5; putih di `brand-dark` 9,1; `alert` di putih 7,4;
  teks menu di `side` 9,2; putih di menu aktif 8,1; judul grup menu 6,0; teks di monogram emas 7,1.

## Zona waktu dan bahasa

`config/app.php` dulu terkunci `'timezone' => 'UTC'`, sehingga jam tampil mundur 7 jam. Sekarang:

- `APP_TIMEZONE=Asia/Jakarta` (WIB), `APP_LOCALE=id`; tanggal tampil "24 Oktober 2026 22:35 WIB". Ada tes yang menjaganya.
- Waktu disimpan di database dalam WIB. **Bila ada data lama yang tersimpan dalam UTC, geser +7 jam.**

## Pengaturan kop dan penanda tangan slip (`/pengaturan`)

Dulu di session, sehingga **hilang saat pengguna keluar** (login sungguhan meng-invalidate session). Sekarang tabel `pengaturan`
(`instansi`, `alamat`, `kota`, `juru`, `juru_nrp`, `lega`), berlaku untuk semua pengguna. Hanya admin dan juru bayar yang bisa mengubah.
Isian "nama operator" dihapus (nama diambil dari login), begitu pula tombol "kembalikan data uji coba".
Slip menampilkan `[NAMA INSTANSI]` dan `[Kota]` sampai pengaturan ini diisi.

## Hal kecil

- Tanda tangan slip memakai tanggal hari ini (bukan "[tanggal terbit]").
- Variabel `$u`/`$w` dipakai komponen ikon (`partials/ikon`); jangan memakai nama itu untuk variabel loop di view
  (variabel anak bocor ke tata letak dan merusak ikon sidebar).
