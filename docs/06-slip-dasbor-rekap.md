# Slip Gaji, Dasbor, dan Rekap & Laporan

Ketiganya dulu membaca `App\Support\Demo` (512 personel karangan). `Demo` **dihapus**; sumber datanya sekarang
`App\Support\DataGaji`, yaitu `hasil.json` dari proses gaji yang sudah selesai. Susunannya mengikuti `sipaga.js` di aplikasi Python.

## Periode

Pemilih periode di Dasbor, Slip, dan Rekap memilih salah satu **proses gaji yang sudah selesai** (disimpan di session; bawaan: yang terbaru).
Tanpa proses selesai, tiap halaman menampilkan "Belum ada gaji yang diproses" dengan tombol mulai proses.

## Slip Gaji (`/slip`)

- Daftar dengan pencarian nama/NRP dan status **Tanpa temuan** / **Perlu dicek** (perlu dicek = hitungan DPP tidak konsisten
  atau ada temuan non-info terkait anggota itu). Status "Sudah dicetak" dihapus: datanya tidak ada di aplikasi Python.
- Pratinjau per anggota: penghasilan (gaji pokok, tunjangan, lauk pauk), potongan negara, **jumlah dibayarkan**, terbilang, sumber
  berkas/halaman/nomor urut, hasil pemeriksaan, dan pencocokan dengan pembukuan.
- Cetak/PDF: satu slip, yang dicentang, atau semua (dibagi 50 per kelompok); slip yang perlu dicek diperingatkan.
- Catatan **"Belum termasuk potongan satuan"** tercetak di setiap slip (sesuai dokumen juru bayar; jangan dihapus).
  Slip memuat angka DPP; potongan satuan ada di Daftar Potongan.

## Dasbor (`/dasbor`)

Anggota menurut DPP, tanpa temuan, perlu dicek, belum ada di pembukuan; rekap per berkas PDF (penghasilan, potongan, dibayarkan);
daftar temuan tinggi ("Perlu perhatian").

## Rekap & Laporan (`/rekap`)

Lima laporan dari data DPP (`App\Support\Laporan`): **rekap bulanan** (per berkas PDF), **daftar penerimaan** (kolom tanda tangan),
**rekap potongan**, **rekap PPh 21**, **daftar temuan**. Tiap laporan: lihat, cetak/PDF (lewat jendela cetak peramban), dan unduh CSV
(`;` dan BOM agar rapi di Excel Indonesia; NRP ditulis `="…"` supaya Excel tidak membuang nol di depan atau memotong 16 digit).
Ada juga rekap bulanan di bawah daftar laporan.

## Berkas utama

`app/Support/DataGaji.php`, `app/Support/Laporan.php`, `app/Http/Controllers/{Dashboard,Slip,Rekap}Controller.php`,
`resources/views/{dasbor,slip,rekap}/`, `resources/views/partials/{slip,periode,belum-ada}.blade.php`.
