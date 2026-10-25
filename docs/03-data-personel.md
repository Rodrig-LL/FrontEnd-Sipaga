# Data Personel

Sebelumnya: 512 personel contoh yang dibuat `Demo::build()` dan disimpan di session. Sekarang: tabel `personel`
yang **selaras dengan `personel_db.py`** aplikasi Python (kolom yang dibutuhkan pencocokan DPP).

## Kolom

`jenis` (TNI/PNS), `nama`, `pangkat`, `nrp` (hanya angka, unik; dipakai mencocokkan DPP), `nrp_asli` (tulisan asli),
`jabatan`, `ket`, `seksi`, `tgl_lahir`, `bank` (BRI/BSI/BTN/kosong), `rekening`, `nama_rekening`,
`status` (`aktif`/`keluar`), `tgl_keluar`, `alasan_keluar`, `tandai` (diisi impor ulang: perlu dicek).

Model hitung-gaji tiruan (tabel pangkat + `Demo`) **dibuang**: gaji kini datang dari angka DPP sebenarnya.

## Halaman (`/personel`)

- Daftar dengan pencarian (nama/NRP), saringan jenis, dan status: aktif, keluar, perlu dicek, aktif tanpa bank.
- Tambah dan ubah (`SimpanPersonelRequest`): NRP/NIP minimal 5 angka dan unik (`11.2214` dan `112214` dianggap sama);
  bank hanya BRI/BSI/BTN; rekening hanya angka; status keluar wajib tanggal dan alasan.
- **Keluar bukan hapus**: yang pindah/pensiun ditandai keluar supaya pencocokan DPP tetap mengenalinya.
- Hapus permanen hanya untuk data salah input (admin dan juru bayar). Isi terakhirnya, beserta ringkasan potongan
  dan pinjamannya, tersimpan di riwayat.

## Riwayat (`/personel/riwayat`)

Setiap tambah, ubah, keluar, aktif kembali, hapus, dan impor dicatat **kolom demi kolom** (lama → baru) beserta
nama pengguna. Tabel `riwayat` dijaga **trigger database**: UPDATE dan DELETE ditolak bahkan lewat SQL langsung
(ada tesnya). Menyimpan tanpa perubahan tidak membuat catatan.

Penulisan lewat `PersonelService` (satu transaksi dengan riwayatnya).

## Berkas utama

`app/Models/Personel.php`, `app/Services/PersonelService.php`, `app/Http/Requests/SimpanPersonelRequest.php`,
`app/Http/Controllers/PersonelController.php`, `resources/views/personel/`.
