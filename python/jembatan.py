"""
JEMBATAN LARAVEL -> PYTHON untuk proses gaji.

Laravel menulis satu berkas JSON berisi tugas, lalu menjalankan:   python jembatan.py <tugas.json>

Tugas:
  pdf        daftar path PDF DPP
  buku       path pembukuan bulan lalu (.xlsx)
  keluar     folder hasil (harus baru/kosong; penjaga timpa di jalankan.proses() tetap berlaku)
  bulan      paksa nama bulan (opsional), mis. "NOVEMBER 2026"
  personel   acuan pencocokan: {"orang": [baris tabel personel], "jenis": "TNI", "diubah": "", "lain": 0}, atau null
  potongan   potret potongan satuan (potongan.snapshot()), atau null = tanpa Daftar Potongan
  hasil_json path berkas hasil yang ditulis jembatan ini

Hasil (hasil_json): {"ok": true, ...ringkas(), ...rinci(), "log": [...], "berkas": [...]}
atau              {"ok": false, "galat": "<pesan Indonesia>", "log": [...]}.
Kode keluar: 0 berhasil, 2 gagal dengan pesan yang layak dibaca juru bayar, 1 galat tak terduga.

Jembatan ini tidak punya logika gaji sendiri: ia hanya memanggil jalankan.proses() dan mengubah
hasilnya dengan aplikasi.ringkas()/rinci(), jalur yang sama dengan aplikasi seret-dan-lepas aslinya.
Database personel tidak pernah dibaca dari sini; acuannya datang dari Laravel lewat tugas.
"""
import json
import sys
import traceback
from pathlib import Path

from aplikasi import rinci, ringkas
from jalankan import proses, siapkan_template


def _tulis(path, data):
    Path(path).write_text(json.dumps(data, ensure_ascii=False, default=str), encoding="utf-8")


def main(argv):
    if len(argv) != 2:
        print("Pemakaian: python jembatan.py <tugas.json>", file=sys.stderr)
        return 1
    tugas = json.loads(Path(argv[1]).read_text(encoding="utf-8"))
    hasil_json = tugas["hasil_json"]
    catatan = []

    def log(baris):
        catatan.append(str(baris))
        print(baris, flush=True)

    try:
        keluar = Path(tugas["keluar"])
        keluar.mkdir(parents=True, exist_ok=True)
        tpl = siapkan_template(log=log)
        pot = tugas.get("potongan")
        if pot is not None:                       # JSON menjadikan kunci id sebagai teks; potongan.py memakai int
            pot = {**pot, "potongan": {int(k): v for k, v in (pot.get("potongan") or {}).items()},
                   "pokok": {int(k): v for k, v in (pot.get("pokok") or {}).items()}}
        h = proses([Path(p) for p in tugas["pdf"]], Path(tugas["buku"]), keluar, bulan=tugas.get("bulan") or None,
                   tpl=tpl, log=log, personel=tugas.get("personel"), potongan=pot)
    except SystemExit as e:
        _tulis(hasil_json, {"ok": False, "galat": str(e.code if e.code is not None else e), "log": catatan})
        return 2
    except Exception as e:                                             # noqa: BLE001
        traceback.print_exc()
        _tulis(hasil_json, {"ok": False, "galat": f"Gagal memproses: {type(e).__name__}: {e}", "log": catatan})
        return 1

    berkas = [{"nama": p.name, "ukuran": p.stat().st_size} for p in sorted(keluar.iterdir())]
    _tulis(hasil_json, {**ringkas(h), **rinci(h), "ok": True, "log": catatan, "berkas": berkas,
                        "sumber": Path(tugas["buku"]).name, "pdf": [Path(p).name for p in tugas["pdf"]]})
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
