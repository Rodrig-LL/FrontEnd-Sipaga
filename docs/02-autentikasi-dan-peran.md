# Autentikasi dan peran

Menggantikan login prototipe (nama pengguna dan kata sandi apa saja, middleware `DemoAuth`).

## Login

- Tabel `users`: `username`, `name`, `password` (di-hash), `role`, `aktif`.
- `POST /masuk` memakai `Auth::attempt` dengan syarat `aktif = true`. Pesan galat **sama** untuk pengguna tak dikenal,
  sandi salah, dan akun nonaktif (tidak membocorkan mana yang benar).
- Dibatasi **5 percobaan per menit** (`throttle:5,1`), lalu HTTP 429.
- Keluar: sesi di-invalidate dan token dibuat ulang. Pengguna yang sudah masuk diarahkan dari `/masuk` ke dasbor.
- Rute di dalam aplikasi memakai middleware `auth`; tamu diarahkan ke `/masuk` (`bootstrap/app.php`).

## Membuat pengguna

```bash
php artisan sipaga:pengguna jurubayar "Nama Lengkap" --peran=juru_bayar    # kata sandi ditanyakan (min. 8 karakter)
```

`DatabaseSeeder` membuat tiga pengguna contoh (`admin`, `jurubayar`, `operator`) **hanya bila `APP_ENV=local`**;
kata sandi pengembangan ada di berkas itu. Jangan dipakai di server.

## Peran dan hak akses

Semua peran yang sudah masuk boleh **melihat** semua halaman dan **mencatat/mengubah personel**.

| Hak (Gate) | admin | juru_bayar | operator |
|---|:-:|:-:|:-:|
| `jalankan-proses` (proses gaji) | ✔ | ✔ | — |
| `hapus-proses` (hapus proses gaji sebelumnya) | ✔ | ✔ | — |
| `impor-personel` (impor Excel) | ✔ | ✔ | — |
| `hapus-personel` | ✔ | ✔ | — |
| `kelola-potongan` (potongan, pinjaman, aturan) | ✔ | ✔ | — |
| `ubah-pengaturan` (kop slip) | ✔ | ✔ | — |

> Pembagian ini adalah **tebakan** (dokumen juru bayar tidak memuat peran, karena aplikasi aslinya satu pemakai).
> Ubah di `AppServiceProvider::boot()` bila perlu.

Nama pengguna yang login dipakai sebagai "siapa" di riwayat, menggantikan isian bebas "nama operator" di Pengaturan.
