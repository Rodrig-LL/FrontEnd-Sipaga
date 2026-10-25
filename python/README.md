# Kode Python juru bayar (jembatan Laravel)

Folder ini berisi kode aplikasi **OTOMASI GAJI DPP** milik juru bayar, disalin **tanpa data** (tanpa `data/personel.db`, PDF,
atau pembukuan asli). Laravel memanggilnya lewat dua skrip tambahan:

- `jembatan.py` — proses gaji (PDF DPP + pembukuan → pembukuan baru, laporan validasi, Daftar Potongan; hasil JSON).
- `jembatan_personel.py` — pratinjau impor Excel (nominatif, bank, potongan, arisan); hasil berupa rencana JSON.

Berkas lain sama dengan aslinya; **jangan diubah tanpa sebab**. Keputusan juru bayar di `jurubayar_konteks.md` (di arsip aslinya)
berlaku: jangan diubah tanpa bertanya. Pemasangan: `python3 -m venv .venv && .venv/bin/pip install -r requirements.txt`.
Pembaca PDF hasil scan (OCR) hanya jalan di Windows. Penjelasan lengkap: `../docs/05-proses-gaji-dan-jembatan-python.md`.
