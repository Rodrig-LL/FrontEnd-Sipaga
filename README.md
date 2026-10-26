# SIPAGA — Slip Gaji Terpadu (Laravel 13 + Blade + Alpine.js + Tailwind 3.4)

Aplikasi web untuk juru bayar: membaca **PDF DPP** gaji, mencocokkannya dengan pembukuan bulan lalu dan **Data Personel**,
lalu menghasilkan **pembukuan baru, laporan validasi, Daftar Potongan**, serta slip, dashboard, dan rekap dari angka DPP.
Logika gaji dikerjakan kode Python juru bayar (`python/`) lewat sebuah jembatan; Laravel menyimpan data, mengatur hak akses,
dan menampilkan hasil. Tampilan ringan untuk laptop lama (Firefox ESR 115): HTML dari server, CSS ±37 KB, JS ±44 KB.

**Dokumentasi per bagian ada di [`docs/`](docs/README.md)** — mulai dari sana. Cara commit dan push: [`docs/10-git-dan-github.md`](docs/10-git-dan-github.md).

## Menjalankan

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
python3 -m venv python/.venv && python/.venv/bin/pip install -r python/requirements.txt
php artisan migrate --seed      # pengguna contoh hanya bila APP_ENV=local
php artisan serve
php artisan test
```

Database bawaan SQLite; MySQL/MariaDB: [`docs/01-database-mysql.md`](docs/01-database-mysql.md).

## Catatan

- Jangan memasukkan data asli (DPP, nominatif, pembukuan) ke repo. `tests/fixtures/` sengaja tidak ikut git (tes yang membutuhkannya dilompati).
- `python/` adalah salinan kode aplikasi Python juru bayar (tanpa data); `python/jembatan*.py` adalah satu-satunya tambahan.
