# Proses gaji dan jembatan Laravel → Python

Logika gaji (membaca PDF DPP, validasi aritmatika, pencocokan, menulis pembukuan baru dan laporan validasi) **tetap
dikerjakan kode Python juru bayar** (`python/`, disalin dari `OTOMASI_GAJI_DPP.zip`, tanpa data). Laravel menyimpan data,
mengatur siapa yang boleh, dan menampilkan hasilnya.

## Pemasangan Python

```bash
python3 -m venv python/.venv
python/.venv/bin/pip install -r python/requirements.txt
```

`config/sipaga.php`: `SIPAGA_PYTHON` (default `python/.venv/bin/python`) dan `SIPAGA_PYTHON_TIMEOUT` (default 1800 detik).
Halaman Proses Gaji memperingatkan bila Python belum terpasang.

## Alur (`/gaji`)

1. **Unggah berkas** (langkah 1): satu atau lebih PDF DPP (satu bank) + satu pembukuan bulan lalu `.xlsx` + bulan (opsional).
   Ditolak bila belum ada personel TNI aktif (acuan pencocokan wajib). Hanya admin dan juru bayar (`jalankan-proses`).
2. **Periksa hasil baca**: per PDF (anggota, konsisten, lembar cocok, gagal dibaca, hasil scan) dan daftar anggota.
3. **Cocokkan data**: pencocokan dengan pembukuan, dengan Data Personel (9 status), dan temuan (Tinggi/Sedang/Info).
4. **Verifikasi selisih**: gaji yang berubah dari pembukuan lama ke angka DPP.
5. **Unduh hasil**: rekonsiliasi, ringkasan potongan satuan, pembukuan baru, laporan validasi, Daftar Potongan.

Wizard demo lama (pilih "angka lama/baru", "tautkan manual", status di session) **dihapus**: fiturnya tidak ada di aplikasi Python,
yang selalu menulis angka DPP (JML DIBAYARKAN).

## Menghapus proses sebelumnya

Di daftar **Proses sebelumnya** (halaman `/gaji`), admin dan juru bayar (`hapus-proses`) bisa menghapus satu proses setelah konfirmasi.

- Yang terhapus: baris di `proses_gaji` **dan** seluruh `storage/app/private/proses/{id}/` (PDF/pembukuan yang diunggah, pembukuan baru, laporan validasi,
  Daftar Potongan, `hasil.json`). Slip, Dashboard, dan Rekap periode itu ikut hilang karena bersumber dari `hasil.json`; bila proses itu sedang dipilih,
  halaman-halaman itu beralih ke proses selesai terbaru berikutnya (atau "belum ada").
- **Unduh dulu** berkas yang masih dibutuhkan.
- Proses yang `menunggu`/`berjalan` tidak bisa dihapus, kecuali macet (tidak ada kemajuan melewati `SIPAGA_PYTHON_TIMEOUT` + 2 menit).
- Tercatat di riwayat (`/personel/riwayat`) sebagai `proses: hapus`: nomor, bulan, status, dan nama berkas.

## Tampilan langkah 1

`/gaji` adalah langkah 1 dari 5 dan memakai stepper (`gaji/_tahap.blade.php`) serta tata letak yang sama dengan langkah 2–5. Langkah 2–5 pada stepper
membuka hasil proses yang sedang dipilih di pemilih periode (nonaktif bila belum ada proses selesai).

## Jembatan

```
Laravel (ProsesGajiController) ── JalankanProsesGaji (antrean) ── JembatanPython
     └─ tulis storage/app/private/proses/{id}/tugas.json
     └─ jalankan: python/.venv/bin/python python/jembatan.py tugas.json     (tanpa shell)
     └─ baca   storage/app/private/proses/{id}/hasil.json   ← ringkas() + rinci() dari aplikasi.py
```

`tugas.json`: `pdf[]`, `buku`, `keluar`, `bulan`, `personel` (acuan TNI), `potongan` (potret potongan satuan), `hasil_json`.
Kode keluar Python: 0 berhasil, 2 gagal dengan pesan yang layak dibaca (ditampilkan apa adanya), 1 galat tak terduga.
`jembatan.py` tidak punya logika gaji sendiri; ia memanggil `jalankan.proses()`, jalur yang sama dengan aplikasi aslinya.

Status proses di tabel `proses_gaji`: `menunggu → berjalan → selesai | gagal`. Halaman status menyegarkan diri tiap 3 detik.

## Antrean

`QUEUE_CONNECTION=sync` (lokal) menjalankan proses langsung di permintaan. Di server pakai `database` dan jalankan
`php artisan queue:work` (PDF scan dengan OCR bisa memakan menit; `$timeout` job mengikuti `SIPAGA_PYTHON_TIMEOUT`).

## Pengaman

- Nama berkas unggahan dibersihkan; dua PDF bernama sama ditolak.
- Setiap proses memakai folder `proses/{id}` sendiri, dan isinya dikosongkan sebelum dipakai. Tanpa itu, id yang dipakai ulang (mis. sesudah
  `migrate:fresh`) mewarisi hasil lama dan memicu penjaga timpa Python ("file hasil sudah ada di folder tujuan").
- Unduhan hanya untuk berkas keluaran Python (`/gaji/{id}/unduh/{nama}` memeriksa daftar); `tugas.json` dan jalur lain 404.
- Batas unggahan di `php.ini` (`upload_max_filesize`, `post_max_size`) perlu dinaikkan; PDF DPP bisa jauh lebih besar dari 2 MB.

## Berkas utama

`app/Services/JembatanPython.php`, `app/Jobs/JalankanProsesGaji.php`, `app/Http/Controllers/ProsesGajiController.php`,
`app/Models/ProsesGaji.php`, `python/jembatan.py`, `resources/views/gaji/`.
