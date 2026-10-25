# Pengujian

```bash
php artisan test
```

Bawaan: SQLite di memori (`phpunit.xml`). **49 tes, 394 pernyataan.** Tes yang butuh Python (proses gaji, impor, potongan)
melompat otomatis bila `python/.venv` belum dipasang.

| Berkas | Isi |
|---|---|
| `LoginTest` | login, pesan galat seragam, akun nonaktif, sandi ter-hash, batas percobaan |
| `PersonelTest` | tambah/ubah/keluar/hapus, validasi, riwayat per kolom, trigger riwayat, hak hapus |
| `ProsesGajiTest` | validasi unggahan, hak akses, jalur penuh dengan Python sungguhan, langkah 2–5, zona waktu |
| `HasilGajiTest` | Dasbor, Slip, Rekap, CSV, pemilih periode, kop slip di database |
| `ImporPersonelTest` | pratinjau tanpa mengubah data, terapkan + riwayat, pratinjau basi, bank, berkas bukan nominatif |
| `PotonganTest` | potongan/pinjaman/pokok/aturan, Daftar Potongan lewat Python, impor potongan dan arisan |

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
