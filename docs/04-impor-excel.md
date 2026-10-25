# Impor Excel dengan pratinjau

Menu: **Data Personel → Impor dari Excel** (`/personel/impor`). Hanya admin dan juru bayar (`impor-personel`).
Hanya berkas **`.xlsx`** (`.xls` butuh LibreOffice di server).

## Empat jenis

| Jenis | Berkas | Efek |
|---|---|---|
| `nominatif` | nominatif militer (NAMA, NRP) dan/atau PNS (NAMA, NIP), maks. dua | NRP/NIP baru ditambah; yang ada diperbarui (nama, pangkat, jabatan, KET, seksi, tgl lahir); yang tidak tercantum lagi **ditandai**, tidak otomatis keluar. Bank/rekening tidak disentuh |
| `bank` | pembukuan (BUKU TNI/PNS) per bank | bank, rekening, nama rekening; bank dibaca dari **nama berkas** (BRI/BSI/BTN); rekening kosong di buku tidak menghapus yang ada |
| `potongan` | pembukuan bulan lalu | potongan tetap dan cicilan bank per orang; pinjaman Koperasi/LURJA yang sedang berjalan dicatat dengan **tenor kosong** |
| `arisan` | DAFTAR ARISAN PERSIT | arisan per orang; yang tidak tercantum lagi **di bank yang sama** berhenti |

## Alur

1. **Unggah** → Python (`python/jembatan_personel.py`) membaca berkas dan menyusun **rencana**. Database **belum berubah**.
2. **Pratinjau** (`/personel/impor/{id}`): kartu ringkasan dan tabel rinci (ditambah, diperbarui, ditandai, bermasalah, tidak ketemu).
3. **Terapkan**: Laravel menulis rencana dalam **satu transaksi**, dengan riwayat per baris
   (`impor: tambah`, `impor: ubah`, `impor: ditandai`, `impor: bank`, `impor: potongan`, `impor: pinjaman`, `impor: arisan`)
   dan satu catatan ringkasan `impor`.

## Pengaman

- **Pratinjau basi ditolak**: bila Data Personel berubah setelah pratinjau dibuat (sidik jari = id riwayat terakhir + jumlah
  personel), tombol Terapkan dinonaktifkan dan server menolak.
- Pratinjau yang sudah diterapkan tidak bisa diterapkan dua kali.
- **Berkas unggahan dibuang** begitu rencana jadi (data pribadi tidak disimpan lebih lama dari perlu); yang tersimpan hanya
  rencananya di tabel `impor_personel`.
- Teks dari Excel dipotong sesuai panjang kolom agar satu sel panjang tidak menggagalkan seluruh impor.

## Cara kerja jembatan

Python tidak menulis ke database Laravel. `jembatan_personel.py` menaruh baris personel (dan potret potongan) dari Laravel ke
database SQLite **sementara**, memanggil `personel_db.rencana_*()` / `potongan.rencana_*()` apa adanya, lalu membuang database
sementara itu. Logika pencocokan 100% milik kode Python juru bayar.

## Berkas utama

`app/Services/ImporPersonelService.php`, `app/Http/Controllers/ImporPersonelController.php`, `app/Models/ImporPersonel.php`,
`python/jembatan_personel.py`, `resources/views/personel/impor/`.
Aturan "sheet pertama yang punya judul NAMA dan NRP/NIP" di pembaca nominatif **belum dikonfirmasi** juru bayar: periksa pratinjau.
