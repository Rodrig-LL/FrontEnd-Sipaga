# Autentikasi dan peran

Menggantikan login prototipe (nama pengguna dan kata sandi apa saja, middleware `DemoAuth`).

## Login

- Tabel `users`: `username`, `name`, `password` (di-hash), `role`, `aktif`.
- `POST /masuk` memakai `Auth::attempt` dengan syarat `aktif = true`. Pesan galat **sama** untuk pengguna tak dikenal,
  sandi salah, dan akun nonaktif (tidak membocorkan mana yang benar).
- Dibatasi **5 percobaan per menit** (`throttle:5,1`), lalu HTTP 429.
- Keluar: sesi di-invalidate dan token dibuat ulang. Pengguna yang sudah masuk diarahkan dari `/masuk` ke dashboard.
- Rute di dalam aplikasi memakai middleware `auth`; tamu diarahkan ke `/masuk` (`bootstrap/app.php`).

## Membuat pengguna

```bash
php artisan sipaga:pengguna jurubayar "Nama Lengkap" --peran=juru_bayar    # atau --peran=admin; kata sandi ditanyakan (min. 8 karakter)
```

`DatabaseSeeder` membuat dua pengguna contoh (`admin` dan `jurubayar`) **hanya bila `APP_ENV=local`**;
kata sandi pengembangan ada di berkas itu. Jangan dipakai di server.

## Peran dan hak akses

Ada **dua peran** dengan pembagian kerja yang jelas (peran Operator sudah dihapus):

- **Admin**: hanya mengurus **akun pengguna** dan membaca **log aktivitas** ([11-admin-akun-dan-log.md](11-admin-akun-dan-log.md)).
  Menunya cuma dua itu; halaman kerja (Dashboard, Proses Gaji, Slip, Data Personel, Potongan, Rekap, Pengaturan)
  dialihkan ke `/akun` oleh middleware `operasional` (`BukanAdmin`). Setelah masuk, Admin langsung ke `/akun`.
- **Juru Bayar**: seluruh pekerjaan gaji dan personel.

| Hak (Gate) | admin | juru_bayar |
|---|:-:|:-:|
| `kelola-akun` (akun pengguna dan log aktivitas) | ✔ | — |
| `jalankan-proses` (proses gaji) | — | ✔ |
| `hapus-proses` (hapus proses gaji sebelumnya) | — | ✔ |
| `impor-personel` (impor Excel) | — | ✔ |
| `hapus-personel` | — | ✔ |
| `kelola-potongan` (potongan, pinjaman, aturan) | — | ✔ |
| `ubah-pengaturan` (kop slip) | — | ✔ |

> Pembagian ini adalah **tebakan** (dokumen juru bayar tidak memuat peran, karena aplikasi aslinya satu pemakai).
> Ubah di `AppServiceProvider::boot()` bila perlu. Sebelumnya Admin ikut memegang hak juru bayar; sekarang tidak.

Akun yang dinonaktifkan Admin dikeluarkan pada permintaan berikutnya, juga bila sedang masuk (middleware `aktif` / `AkunAktif`).

Nama pengguna yang login dipakai sebagai "siapa" di riwayat, menggantikan isian bebas "nama operator" di Pengaturan.

### Peran Operator dihapus

Migrasi `2026_10_25_000003_hapus_peran_operator` mengubah akun Operator yang sudah ada menjadi **Juru Bayar yang dinonaktifkan**,
supaya tidak ada yang diam-diam mendapat hak lebih besar. Admin memutuskan di Akun Pengguna (aktifkan, atau biarkan nonaktif).
Admin juga bisa menghapusnya (lihat [11-admin-akun-dan-log.md](11-admin-akun-dan-log.md)); namanya tetap tertulis di riwayat dan log. Pengguna contoh `operator` juga dihapus dari seeder.
Gate tetap dipertahankan walau kini hanya Juru Bayar yang memegangnya (Admin dialihkan sebelum sampai ke sana).
