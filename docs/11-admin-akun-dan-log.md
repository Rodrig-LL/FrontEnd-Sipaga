# Admin: akun pengguna dan log aktivitas

Peran **Admin** hanya menampilkan dua menu (grup **ADMINISTRASI** di sidebar): **Akun Pengguna** dan **Log Aktivitas**.
Hak aksesnya ada di [02-autentikasi-dan-peran.md](02-autentikasi-dan-peran.md).

## Akun pengguna (`/akun`)

`AkunController`, view `akun/index` dan `akun/form`. Dilindungi `can:kelola-akun`; peran lain mendapat 403.

- **Tambah akun**: nama lengkap, nama pengguna (huruf, angka, `.` `_` `-`; unik), peran, kata sandi (minimal 8 karakter, diulang dua kali).
- **Ubah akun**: nama, nama pengguna, peran, status aktif, dan kata sandi baru. Kata sandi dikosongkan = tidak diganti.
- Akun **tidak dihapus**, hanya dinonaktifkan, supaya namanya di log dan riwayat tetap bisa ditelusuri.
- Admin **tidak bisa menurunkan atau menonaktifkan akunnya sendiri** (kolomnya dikunci di form dan diabaikan di server).
  Karena pelakunya selalu Admin aktif, selalu ada minimal satu Admin aktif.
- Akun yang dinonaktifkan langsung dikeluarkan pada permintaan berikutnya (`AkunAktif`).
- Akun pertama tetap dibuat lewat `php artisan sipaga:pengguna <username> "<Nama>" --peran=admin`.

## Log aktivitas (`/log`)

Tabel `log_aktivitas` (migrasi `2026_10_25_000002`), model `LogAktivitas`. Seperti `riwayat`, **hanya bisa ditambah**:
trigger database menolak UPDATE dan DELETE (SQLite `RAISE(ABORT)`, MySQL/MariaDB `SIGNAL SQLSTATE '45000'`).
Kolom: `waktu`, `user_id` (bukan FK), `pengguna` dan `username` (saat kejadian), `aksi` (kode), `keterangan`, `ip`.

Dicatat lewat `LogAktivitas::catat($aksi, $keterangan)`. Yang tercatat:

| Kode | Kejadian |
|---|---|
| `masuk`, `masuk_gagal`, `keluar` | login berhasil, percobaan gagal (nama pengguna yang diketik, **tanpa kata sandi**), keluar |
| `akun_tambah`, `akun_ubah` | Admin membuat/mengubah akun (ringkasan perubahan; kata sandi hanya ditulis "diganti") |
| `gaji_jalan`, `gaji_selesai`, `gaji_gagal`, `gaji_hapus` | proses gaji dijalankan, selesai/gagal (dicatat oleh job atas nama pengunggah), dihapus |
| `impor` | impor Excel diterapkan, beserta ringkasan jumlahnya |
| `personel_tambah`, `personel_ubah`, `personel_hapus` | pencatatan personel (nama saja; rincian per kolom ada di riwayat personel) |
| `potongan`, `pengaturan` | potongan satuan dan kop slip diubah |

Hanya perubahan yang benar-benar terjadi yang dicatat (simpan tanpa perubahan tidak). Halaman log memuat pencarian
(nama, nama pengguna, keterangan, IP), saringan jenis aktivitas, dan paginasi 25 baris. Log ini melengkapi, bukan
menggantikan, **riwayat personel** (`riwayat`) yang menyimpan nilai lama dan baru per kolom.

## Catatan

- Log tidak dibersihkan otomatis. Bila kelak terlalu besar, tambahkan arsip berkala; jangan menghapus lewat SQL (ditolak trigger).
- Untuk memverifikasi tampilan tanpa menyentuh data asli, jalankan server kedua dengan SQLite sementara
  (`DB_CONNECTION=sqlite DB_DATABASE=/jalur/uji.sqlite php artisan migrate && php artisan serve --port=8001`).
