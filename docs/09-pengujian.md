# Pengujian

```bash
php artisan test
```

Bawaan: SQLite di memori (`phpunit.xml`). **74 tes, 622 pernyataan** (sama di SQLite dan MariaDB 10.4). Tes yang butuh Python (proses gaji, impor, potongan)
melompat otomatis bila `python/.venv` belum dipasang.

| Berkas | Tes | Isi |
|---|:-:|---|
| `LoginTest` | 9 | login, pesan galat seragam, akun nonaktif, sandi ter-hash, batas percobaan, keluar, URL lama `/dasbor` dialihkan ke `/dashboard`, identitas instansi di halaman masuk |
| `PersonelTest` | 12 | tambah/ubah/keluar/hapus, validasi, riwayat per kolom, trigger riwayat, hak hapus, daftar dan saringan, pengurutan (nama, pangkat/gol., jenis), peringkat pangkat, halaman riwayat memuat semua jenis catatan |
| `ProsesGajiTest` | 13 | validasi unggahan, hak akses, jalur penuh dengan Python sungguhan, langkah 2–5, sisa folder proses lama, hapus proses (hak akses, tidak bisa saat berjalan kecuali macet, riwayat), stepper langkah 1, zona waktu WIB |
| `HasilGajiTest` | 7 | Dashboard, Slip, Rekap, CSV, pemilih periode, kop slip di database, halaman tanpa proses |
| `ImporPersonelTest` | 8 | pratinjau tanpa mengubah data, terapkan + riwayat, pratinjau basi, bank, berkas bukan nominatif, hak akses |
| `AdminTest` | 16 | Admin hanya akun dan log (menu, pengalihan halaman kerja, 403 untuk peran lain), buat/ubah akun dan validasi, tidak bisa menurunkan diri sendiri, akun nonaktif dikeluarkan, log (masuk/gagal/keluar tanpa kata sandi, pekerjaan, saringan, trigger), nama lengkap di sidebar, ikon tab, ukuran ikon menu di halaman ubah akun, hapus akun (jejak riwayat dan log tetap, tidak bisa hapus diri sendiri) |
| `PotonganTest` | 8 | potongan/pinjaman/pokok/aturan, halaman, hapus personel (cascade), Daftar Potongan lewat Python, impor potongan dan arisan |

Perintah `php artisan test --filter <nama>` menjalankan satu tes. Tes yang memakai Python memanggil `jembatan.py`/`jembatan_personel.py` sungguhan
(sekitar 1–2 detik per proses), jadi seluruh rangkaian selesai dalam beberapa detik.

## Menjalankan di MariaDB/MySQL

Penting: SQLite tidak memeriksa panjang kolom dan tipe seketat MariaDB (bug `personel.status` ketahuan hanya di MariaDB).
Buat database `sipaga_test` (lihat [01-database-mysql.md](01-database-mysql.md)), lalu:

```bash
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=sipaga_test DB_USERNAME=sipaga \
DB_PASSWORD='...' DB_SOCKET=/opt/lampp/var/mysql/mysql.sock php artisan test
```

Variabel lingkungan menang atas `<env>` di `phpunit.xml`. Tes memakai `RefreshDatabase`, jadi **jangan** arahkan ke database kerja.

## Data uji

`tests/fixtures/` seluruhnya **sintetis**: PDF DPP contoh dan pembukuan contoh (`dpp/`, dari `CONTOH_UJI` aplikasi Python), serta nominatif,
daftar arisan, dan pembukuan potongan buatan (`impor/`). Angka acuan: 10 anggota, total dibayarkan Rp 70.998.220, Rp 42.167.540 yang cocok
dengan pembukuan, 14 temuan tinggi (sama dengan dokumen konteks juru bayar).
