# Potongan satuan dan Daftar Potongan

Potongan di luar DPP: simpanan wajib dan pokok koperasi, cicilan Koperasi Ditajenad dan LURJA, cicilan bank (BRI A Yani, BRI AA,
BJB, BWS, BTN), iuran tetap (TWP, Persit, Kowad, Korpri, PMI, dll.), dan arisan Persit. Aturan hitungnya milik `python/potongan.py`
(keputusan juru bayar 16–17 Okt 2026, bagian 4i `jurubayar_konteks.md`); Laravel menyimpan datanya.

## Data (tabel)

| Tabel | Isi |
|---|---|
| `potongan` | `personel_id`, `kode` (jenis), `nominal`; 0 = baris dihapus |
| `pinjaman` | Koperasi/LURJA: `cicilan`, `mulai` (YYYY-MM), `tenor` (kosong = belum diketahui), `status` aktif/lunas/batal |
| `pokok` | simpanan pokok dipotong sekali di `bulan` |
| `aturan_potongan` | `sisa_ybs`, `pokok`, `batas` per pemberi, `arisan` (catatan impor terakhir per bank) |

Menghapus personel menghapus ikutannya (cascade); ringkasannya masuk riwayat. Daftar jenis ada di `PotonganData::JENIS`
dan **harus sama** dengan `potongan.JENIS` di Python (kode dipakai di dua sisi).

## Halaman (`/potongan`, menu **Potongan Satuan**)

- **Per orang**: daftar personel aktif; "Ubah" membuka form potongan tetap per jenis, pinjaman (catat/ubah/lunas/batal), simpanan pokok.
- **Pinjaman**: semua pinjaman dengan keadaan pada periode proses ("cicilan ke-4 dari 12", "tenor belum diisi", "selesai").
- **Aturan**: sisa gaji minimal (YBS), nominal simpanan pokok bawaan, batas pinjam per pemberi (maks % gaji, sisa gaji minimal, tenor maks).
- Ringkasan **Daftar Potongan terakhir**: orang dihitung, tunggakan koperasi, gaji masih minus, MASS DEBET, dan peringatan.
- Ubah/catat: hanya admin dan juru bayar (`kelola-potongan`); operator hanya melihat. Setiap perubahan tercatat di riwayat.

Isi awal dari pembukuan bulan lalu dan arisan lewat impor Excel ([04-impor-excel.md](04-impor-excel.md)).

## Perhitungan saat proses gaji

`JembatanPython` mengirim potret (`PotonganData::snapshot()`) ke Python; `jalankan.proses()` memanggil `potongan.daftar_proses()` dan menulis
`DAFTAR_POTONGAN_<BANK>_BLN_<BULAN>.xlsx` (tabel kolom buku, sheet TUNGGAKAN, MASS DEBET siap kirim bank). Proses gaji **tidak pernah**
menulis ke tabel potongan. Aturan utama:

- Potongan melebihi gaji → **YBS disisakan Rp 5.000** (bisa diatur), **Koperasi Ditajenad yang menunggak** (simpanan wajib dulu, lalu cicilan);
  LURJA dan potongan lain dipotong penuh. Tunggakan **hanya dicatat**; masih minus setelah koperasi nol → peringatan TINGGI, ditulis apa adanya.
- Pinjaman impor dengan tenor kosong tetap dipotong tiap bulan sampai tenor diisi.
- Batas pinjam yang terlewati hanya **peringatan**.
- Orang yang tidak ada di DPP atau tidak ada di Data Personel: potongan dibiarkan kosong, peringatan SEDANG.

Hasil muncul di langkah 5 proses gaji dan di `/potongan`. Slip tidak memuat potongan satuan.

## Belum ada

Potongan massal, cek batas pinjam langsung di form, MASS DEBET BSI/BTN per jenis, pengimpor daftar cicilan bank.

## Berkas utama

`app/Services/PotonganService.php`, `app/Support/PotonganData.php`, `app/Http/Controllers/PotonganController.php`,
`app/Models/Pinjaman.php`, `resources/views/potongan/`, `python/potongan.py`.
