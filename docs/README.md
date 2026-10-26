# Dokumentasi perubahan SIPAGA

Repo ini awalnya hanya **frontend** (Blade + Alpine + Tailwind) dengan data contoh di *session*. Sekarang ia
**proyek Laravel 13 utuh** dengan backend: login, database, dan proses gaji yang dikerjakan aplikasi Python
juru bayar (`python/`) lewat sebuah jembatan.

Satu berkas per bagian yang berubah. Baca urutannya bila baru pertama kali:

| Berkas | Bagian |
|---|---|
| [01-database-mysql.md](01-database-mysql.md) | Database MySQL/MariaDB (XAMPP), skema, dan cara pindah dari SQLite |
| [02-autentikasi-dan-peran.md](02-autentikasi-dan-peran.md) | Login sungguhan, tiga peran, hak akses |
| [03-data-personel.md](03-data-personel.md) | Data Personel, riwayat perubahan yang tak bisa diubah |
| [04-impor-excel.md](04-impor-excel.md) | Impor nominatif, bank/rekening, potongan, dan arisan (dengan pratinjau) |
| [05-proses-gaji-dan-jembatan-python.md](05-proses-gaji-dan-jembatan-python.md) | Proses gaji 5 langkah dan jembatan Laravel ke Python |
| [06-slip-dashboard-rekap.md](06-slip-dashboard-rekap.md) | Slip Gaji, Dashboard, Rekap & Laporan dari data DPP nyata |
| [07-potongan-satuan.md](07-potongan-satuan.md) | Potongan satuan dan Daftar Potongan |
| [08-tampilan-waktu-pengaturan.md](08-tampilan-waktu-pengaturan.md) | Logo dan palet, zona waktu WIB, pengaturan kop slip |
| [09-pengujian.md](09-pengujian.md) | Cara menjalankan tes (SQLite dan MariaDB) |
| [10-git-dan-github.md](10-git-dan-github.md) | Alur commit dan push ke GitHub (token, cabang, yang tidak boleh ikut) |
| [11-admin-akun-dan-log.md](11-admin-akun-dan-log.md) | Peran Admin: kelola akun dan log aktivitas |

## Menjalankan dari nol

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
python3 -m venv python/.venv && python/.venv/bin/pip install -r python/requirements.txt
php artisan migrate --seed          # pengguna contoh hanya dibuat saat APP_ENV=local
php artisan serve
```

Database bawaan SQLite. Untuk MySQL/MariaDB lihat [01-database-mysql.md](01-database-mysql.md).
Di server, buat pengguna dengan `php artisan sipaga:pengguna <username> "<Nama Lengkap>" --peran=juru_bayar`
(kata sandi ditanyakan; tidak ada kata sandi tertulis di kode).

## Hal yang sengaja tidak ada di repo

- **Data asli.** `OTOMASI_GAJI_DPP.zip` memuat data pribadi personel dan **tidak boleh** ikut ter-commit
  (di mesin pengembang sudah di `.git/info/exclude`). `python/` hanya berisi kode, tanpa `data/`.
- Berkas uji (`tests/fixtures/`, data karangan) **tidak disimpan di repo**; tes yang membutuhkannya dilompati. Lihat [09-pengujian.md](09-pengujian.md).
- Berkas yang diunggah ke proses gaji disimpan di `storage/app/private/proses/` (tidak ikut git).
  Berkas impor Excel dibuang segera setelah dibaca.

## Yang belum ada

- Potongan massal dan cek batas pinjam langsung di form per orang.
- MASS DEBET khusus BSI/BTN per jenis potongan; pengimpor daftar cicilan bank.
- Pembaca DPP PNS (di sisi Python), sehingga slip, laporan, dan dashboard masih TNI saja.
- Pembacaan PDF hasil scan (OCR) hanya jalan di Windows; di Linux hanya PDF bertekslah.
- Belum diuji dengan berkas DPP, nominatif, pembukuan, dan arisan asli.
