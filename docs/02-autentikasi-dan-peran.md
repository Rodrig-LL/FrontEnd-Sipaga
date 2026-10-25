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
php artisan sipaga:pengguna jurubayar "Nama Lengkap" --peran=juru_bayar    # kata sandi ditanyakan (min. 8 karakter)
```

`DatabaseSeeder` membuat tiga pengguna contoh (`admin`, `jurubayar`, `operator`) **hanya bila `APP_ENV=local`**;
kata sandi pengembangan ada di berkas itu. Jangan dipakai di server.

## Peran dan hak akses

Ada tiga peran dengan pembagian kerja yang jelas:

- **Admin**: hanya mengurus **akun pengguna** dan membaca **log aktivitas** ([11-admin-akun-dan-log.md](11-admin-akun-dan-log.md)).
  Menunya cuma dua itu; halaman kerja (Dashboard, Proses Gaji, Slip, Data Personel, Potongan, Rekap, Pengaturan)
  dialihkan ke `/akun` oleh middleware `operasional` (`BukanAdmin`). Setelah masuk, Admin langsung ke `/akun`.
- **Juru Bayar**: seluruh pekerjaan gaji dan personel.
- **Operator**: **melihat** semua halaman kerja dan **mencatat/mengubah personel**; tidak bisa menjalankan atau menghapus apa pun yang berat.

| Hak (Gate) | admin | juru_bayar | operator |
|---|:-:|:-:|:-:|
| `kelola-akun` (akun pengguna dan log aktivitas) | ✔ | — | — |
| `jalankan-proses` (proses gaji) | — | ✔ | — |
| `hapus-proses` (hapus proses gaji sebelumnya) | — | ✔ | — |
| `impor-personel` (impor Excel) | — | ✔ | — |
| `hapus-personel` | — | ✔ | — |
| `kelola-potongan` (potongan, pinjaman, aturan) | — | ✔ | — |
| `ubah-pengaturan` (kop slip) | — | ✔ | — |

> Pembagian ini adalah **tebakan** (dokumen juru bayar tidak memuat peran, karena aplikasi aslinya satu pemakai).
> Ubah di `AppServiceProvider::boot()` bila perlu. Sebelumnya Admin ikut memegang hak juru bayar; sekarang tidak.

Akun yang dinonaktifkan Admin dikeluarkan pada permintaan berikutnya, juga bila sedang masuk (middleware `aktif` / `AkunAktif`).

Nama pengguna yang login dipakai sebagai "siapa" di riwayat, menggantikan isian bebas "nama operator" di Pengaturan.
