# Database MySQL/MariaDB

## Ringkas

- Bawaan proyek: **SQLite** (`DB_CONNECTION=sqlite`), cukup untuk mencoba.
- Dipakai sekarang: **MariaDB 10.4.32** dari XAMPP (`/opt/lampp`), lewat driver `mysql` Laravel.
- Seluruh tes lulus di **kedua** database (74 tes, 622 pernyataan).

## Cocok dengan Laravel 13?

Ya. Kode Laravel 13 (`MariaDbGrammar`) hanya membatasi versi MariaDB untuk dua fitur, dan keduanya punya jalur
cadangan untuk 10.4: ganti nama kolom di bawah **10.5.2** dan tipe `uuid` di bawah **10.7.0**. Aplikasi ini memakai
keduanya tidak. Bukti sebenarnya: semua migrasi (termasuk trigger) dan seluruh tes berjalan di MariaDB 10.4.32.

Catatan: MariaDB 10.4 sudah habis masa dukungannya (EOL Juni 2024). Untuk server produksi pakai versi yang masih
didukung (MariaDB 10.11+ atau MySQL 8).

## Hal khusus XAMPP Linux

- MariaDB XAMPP berjalan dengan **`skip_networking=ON`**: port 3306 **tertutup**. Aplikasi tersambung lewat
  **socket**: `DB_SOCKET=/opt/lampp/var/mysql/mysql.sock`. `DB_HOST`/`DB_PORT` diabaikan bila socket diisi.
- Menyalakan: `sudo /opt/lampp/lampp startmysql`.
- Server yang sama dipakai proyek lain (database lain di dalamnya). SIPAGA hanya memakai `sipaga` dan `sipaga_test`.

## Yang dibuat di server

| Objek | Keterangan |
|---|---|
| database `sipaga` | data kerja (`utf8mb4_unicode_ci`) |
| database `sipaga_test` | hanya untuk menjalankan tes di MariaDB |
| pengguna `sipaga`@`localhost` | hak penuh **hanya** pada dua database di atas; kata sandi acak, hanya ada di `.env` |

`root` XAMPP tidak berkata sandi, jadi aplikasi **tidak** memakainya.

## Pindah dari SQLite ke MySQL

1. Buat database dan pengguna (ganti `GANTI_SANDI`):
   ```sql
   CREATE DATABASE sipaga CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'sipaga'@'localhost' IDENTIFIED BY 'GANTI_SANDI';
   GRANT ALL PRIVILEGES ON sipaga.* TO 'sipaga'@'localhost';
   ```
   Hak `TRIGGER` diperlukan (termasuk di `ALL`): riwayat dijaga trigger.
2. Isi `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=sipaga`, `DB_USERNAME=sipaga`, `DB_PASSWORD=...`,
   dan `DB_SOCKET=/opt/lampp/var/mysql/mysql.sock` (atau `DB_HOST`/`DB_PORT` bila jaringan MySQL aktif).
3. `php artisan config:clear && php artisan migrate --seed`.

Data uji di SQLite **tidak ikut pindah** (data sintetis; buat ulang lewat impor atau proses gaji).

## Skema (migrasi di `database/migrations/`)

| Tabel | Isi |
|---|---|
| `users` | pengguna: `username`, `role` (admin/juru_bayar), `aktif` |
| `sessions`, `cache`, `jobs` | bawaan Laravel (sesi dan antrean di database) |
| `personel` | master personel (selaras `personel_db.py` Python) |
| `riwayat` | catatan perubahan; **trigger** menolak UPDATE/DELETE |
| `log_aktivitas` | log aktivitas aplikasi untuk Admin; **trigger** menolak UPDATE/DELETE |
| `proses_gaji` | satu baris per proses gaji; hasil rinci di `storage/.../hasil.json` |
| `pengaturan` | kop dan penanda tangan slip (kunci–nilai) |
| `impor_personel` | pratinjau impor Excel (rencana dari Python) |
| `potongan`, `pinjaman`, `pokok`, `aturan_potongan` | potongan satuan |

Trigger riwayat memakai sintaks berbeda per driver (SQLite `RAISE(ABORT)`, MySQL/MariaDB `SIGNAL SQLSTATE '45000'`);
keduanya dipasang oleh migrasi `2026_10_24_000001` (riwayat), `2026_10_25_000002` (log aktivitas), dan `2026_10_25_000004` (dipasang ulang setelah FK `riwayat.user_id` dilepas, supaya akun bisa dihapus).

## Bug yang ketahuan karena pindah ke MariaDB

`personel.status` semula `string(5)`, padahal nilai `keluar` panjangnya 6. SQLite tidak memeriksa panjang kolom,
MariaDB (mode ketat) menolak: menandai personel keluar akan gagal. Sudah diperbaiki menjadi `string(10)`.
Pelajaran: **jalankan tes di MariaDB**, bukan hanya SQLite (lihat [09-pengujian.md](09-pengujian.md)).
