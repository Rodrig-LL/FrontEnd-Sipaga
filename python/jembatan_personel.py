"""
JEMBATAN LARAVEL -> PYTHON untuk impor Data Personel (pratinjau saja).

Laravel menulis berkas JSON berisi tugas, lalu menjalankan:   python jembatan_personel.py <tugas.json>

Tugas:
  mode        "nominatif" (Excel nominatif militer dan/atau PNS), "bank" (bank dan rekening dari pembukuan),
              "potongan" (potongan satuan dari pembukuan bulan lalu) atau "arisan" (DAFTAR ARISAN PERSIT)
  berkas      daftar path berkas .xlsx
  personel    baris tabel personel saat ini (kunci sama dengan kolom personel_db.KOLOM, ditambah "id")
  potongan    (mode potongan/arisan) potret potongan satuan: potongan, pinjaman, pokok, aturan
  hasil_json  path berkas hasil

Hasil: {"ok": true, "rencana": {...}} atau {"ok": false, "galat": "<pesan Indonesia>"}.
Kode keluar: 0 berhasil, 2 gagal dengan pesan yang layak dibaca, 1 galat tak terduga.

Logika pencocokan sepenuhnya milik personel_db.rencana_nominatif()/rencana_bank(); jembatan ini hanya
menaruh baris personel dari Laravel ke database SQLite sementara supaya fungsi itu bisa dipakai apa
adanya. Database sementara dibuang sesudahnya, dan jembatan ini TIDAK menulis apa pun ke database
Laravel: rencananya diterapkan oleh Laravel (ImporPersonelService), lengkap dengan riwayatnya.
"""
import json
import sys
import tempfile
import traceback
from pathlib import Path

import personel_db
import potongan


def _tulis(path, data):
    Path(path).write_text(json.dumps(data, ensure_ascii=False, default=str), encoding="utf-8")


def _isi_sementara(baris):
    kolom = ("id",) + personel_db.KOLOM + ("dibuat", "diubah")
    with personel_db._sambung() as con:
        for b in baris:
            v = {k: ("" if b.get(k) is None else b.get(k)) for k in kolom}
            v["dibuat"] = v["dibuat"] or "2000-01-01 00:00:00"
            v["diubah"] = v["diubah"] or "2000-01-01 00:00:00"
            con.execute(f"INSERT INTO personel ({', '.join(kolom)}) VALUES ({', '.join('?' * len(kolom))})",
                        [v[k] for k in kolom])


def _isi_potongan(snap):
    with personel_db._sambung() as con:
        for pid, kv in (snap.get("potongan") or {}).items():
            for kode, nominal in kv.items():
                con.execute("INSERT INTO potongan (personel_id, kode, nominal) VALUES (?, ?, ?)", (int(pid), kode, nominal))
        for x in snap.get("pinjaman") or []:
            con.execute("INSERT INTO pinjaman (id, personel_id, pemberi, cicilan, mulai, tenor, tgl_cair, catatan, status, dibuat, diubah) "
                        "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        (x["id"], x["personel_id"], x["pemberi"], x["cicilan"], x["mulai"], x.get("tenor"), x.get("tgl_cair") or "",
                         x.get("catatan") or "", x.get("status") or "aktif", x.get("dibuat") or "2000-01-01 00:00:00",
                         x.get("diubah") or "2000-01-01 00:00:00"))
        for kunci, nilai in (snap.get("aturan") or {}).items():
            con.execute("INSERT INTO aturan (kunci, nilai) VALUES (?, ?)", (kunci, json.dumps(nilai, ensure_ascii=False)))


def main(argv):
    if len(argv) != 2:
        print("Pemakaian: python jembatan_personel.py <tugas.json>", file=sys.stderr)
        return 1
    tugas = json.loads(Path(argv[1]).read_text(encoding="utf-8"))
    hasil_json = tugas["hasil_json"]
    fungsi = {"nominatif": personel_db.rencana_nominatif, "bank": personel_db.rencana_bank,
              "potongan": potongan.rencana_buku, "arisan": potongan.rencana_arisan}.get(tugas["mode"])
    if not fungsi:
        _tulis(hasil_json, {"ok": False, "galat": f"Mode impor tidak dikenal: {tugas['mode']!r}."})
        return 2
    with tempfile.TemporaryDirectory(prefix="impor_personel_") as tmp:
        try:
            personel_db.atur_path(Path(tmp) / "personel.db")
            _isi_sementara(tugas["personel"])
            if tugas.get("potongan"):
                _isi_potongan(tugas["potongan"])
            rencana = fungsi([Path(p) for p in tugas["berkas"]])
        except SystemExit as e:
            _tulis(hasil_json, {"ok": False, "galat": str(e.code if e.code is not None else e)})
            return 2
        except Exception as e:                                         # noqa: BLE001
            traceback.print_exc()
            _tulis(hasil_json, {"ok": False, "galat": f"Gagal membaca berkas: {type(e).__name__}: {e}"})
            return 1
    _tulis(hasil_json, {"ok": True, "rencana": rencana})
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv))
