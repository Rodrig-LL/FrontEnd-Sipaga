# Data Personel

Sebelumnya: 512 personel contoh yang dibuat `Demo::build()` dan disimpan di session. Sekarang: tabel `personel`
yang **selaras dengan `personel_db.py`** aplikasi Python (kolom yang dibutuhkan pencocokan DPP).

## Kolom

`jenis` (TNI/PNS), `nama`, `pangkat`, `nrp` (hanya angka, unik; dipakai mencocokkan DPP), `nrp_asli` (tulisan asli),
`jabatan`, `ket`, `seksi`, `tgl_lahir`, `bank` (BRI/BSI/BTN/kosong), `rekening`, `nama_rekening`,
`status` (`aktif`/`keluar`), `tgl_keluar`, `alasan_keluar`, `tandai` (diisi impor ulang: perlu dicek).

Model hitung-gaji tiruan (tabel pangkat + `Demo`) **dibuang**: gaji kini datang dari angka DPP sebenarnya.

## Halaman (`/personel`)

- Daftar dengan satu kotak pencarian yang memuat **Cari** (nama/NRP), **Status** (aktif, keluar, perlu dicek, aktif tanpa bank), dan
  **Jenis keanggotaan** (Semua, TNI, PNS, lengkap dengan jumlahnya). Ketiganya dikirim bersama dan langsung berlaku saat pilihan diganti.
- **Urutkan** menurut **Nama**, **Pangkat / Gol.**, atau **Jenis keanggotaan**, dengan pilihan arah (parameter `urut` dan `arah`, ikut terbawa saat berpindah halaman).
  Pengurutan **hanya lewat judul kolom** di tabel (`partials/th-urut`): panah ▲/▼ menandai kolom yang aktif (`aria-sort`), ↕ kolom lain.
  Klik kolom aktif membalik arahnya; klik kolom lain memulai dari arah bawaannya dan kembali ke halaman 1. Kotak pencarian tidak punya pemilih
  urutan, tetapi membawa urutan yang aktif (input tersembunyi) supaya mencari atau menyaring tidak mengubahnya.


  | Urutkan | Naik | Turun |
  |---|---|---|
  | Nama | A → Z (huruf besar/kecil dianggap sama) | Z → A |
  | Pangkat / Gol. | Terendah dulu | Tertinggi dulu (**bawaan** untuk pangkat) |
  | Jenis keanggotaan (**bawaan**) | TNI dulu | PNS dulu |

  Urutan yang sama selalu jatuh ke nama. Peringkat pangkat dibaca oleh `App\Support\Pangkat` dari teks bebasnya: kata pertama untuk TNI
  (Prada … Jenderal; Pelda dan Peltu di atas Serma, di bawah Letda) dan penulisan golongan untuk PNS (`III/b`, `IV/a`, dst.).
  Pangkat TNI dan golongan PNS tidak sebanding, jadi keduanya tetap dikelompokkan per jenis (TNI dulu); pangkat kosong atau tak dikenal
  selalu di paling bawah kelompoknya. Mengganti kriteria mengembalikan arah ke bawaannya.
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

Catatan **impor** (`impor`, ringkasan satu impor Excel) isinya nilai biasa (jenis, jumlah, daftar berkas), bukan pasangan lama → baru.
Halaman ini dulu error begitu catatan seperti itu muncul di halaman pertama; kini kedua bentuk ditampilkan (ada tes regresinya).

## Berkas utama

`app/Models/Personel.php`, `app/Services/PersonelService.php`, `app/Http/Requests/SimpanPersonelRequest.php`,
`app/Http/Controllers/PersonelController.php`, `resources/views/personel/`.
