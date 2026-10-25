<?php

namespace App\Services;

use App\Models\Personel;
use App\Models\Riwayat;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Tulis personel dan catat riwayatnya dalam satu transaksi (padanan simpan()/hapus() di personel_db.py). */
class PersonelService
{
    /**
     * Tambah (tanpa $personel) atau ubah satu personel.
     *
     * @param  array  $v  nilai yang sudah dibersihkan dan divalidasi (SimpanPersonelRequest::nilai())
     * @return array{0: Personel, 1: bool}  [personel, apakah ada yang berubah]
     */
    public function simpan(array $v, User $oleh, ?Personel $personel = null): array
    {
        return DB::transaction(function () use ($v, $oleh, $personel) {
            foreach (['tgl_lahir', 'tgl_keluar'] as $k) {
                $v[$k] = ($v[$k] ?? '') === '' ? null : $v[$k];
            }

            if (! $personel) {
                $p = Personel::create($v);
                $p->refresh();
                $this->catat('tambah', $oleh, $p, collect($p->nilaiTeks())->filter(fn ($x) => $x !== '')
                    ->map(fn ($x) => ['', $x])->all());

                return [$p, true];
            }

            $p = Personel::lockForUpdate()->findOrFail($personel->id);
            $lama = $p->nilaiTeks();
            $p->fill($v)->save();
            $p->refresh();
            $baru = $p->nilaiTeks();
            $beda = [];
            foreach (Personel::KOLOM_RIWAYAT as $k) {
                if ($lama[$k] !== $baru[$k]) $beda[$k] = [$lama[$k], $baru[$k]];
            }
            if (! $beda) return [$p, false];

            $aksi = match (true) {
                $lama['status'] === 'aktif' && $baru['status'] === 'keluar' => 'keluar',
                $lama['status'] === 'keluar' && $baru['status'] === 'aktif' => 'aktif kembali',
                default => 'ubah',
            };
            $this->catat($aksi, $oleh, $p, $beda);

            return [$p, true];
        });
    }

    /** Hapus permanen (untuk data salah input). Isi terakhirnya disimpan di riwayat. */
    public function hapus(Personel $personel, User $oleh): void
    {
        DB::transaction(function () use ($personel, $oleh) {
            $p = Personel::lockForUpdate()->findOrFail($personel->id);
            $isi = collect($p->nilaiTeks())->filter(fn ($x) => $x !== '')->map(fn ($x) => [$x, ''])->all();
            // Potongan, pinjaman, dan simpanan pokoknya ikut terhapus (cascade); ringkasannya disimpan di riwayat.
            $pot = DB::table('potongan')->where('personel_id', $p->id)->get();
            if ($pot->isNotEmpty()) $isi['potongan'] = [$pot->map(fn ($x) => $x->kode.' '.$x->nominal)->implode(', '), ''];
            $pj = DB::table('pinjaman')->where('personel_id', $p->id)->get();
            if ($pj->isNotEmpty()) $isi['pinjaman'] = [$pj->map(fn ($x) => "{$x->pemberi} {$x->cicilan}/bln ({$x->status})")->implode(', '), ''];
            $this->catat('hapus', $oleh, $p, $isi);
            $p->delete();
        });
    }

    public function catat(string $aksi, User $oleh, Personel $p, array $perubahan): void
    {
        Riwayat::create([
            'user_id' => $oleh->id, 'operator' => $oleh->name, 'aksi' => $aksi,
            'personel_id' => $p->id, 'nrp' => $p->nrp, 'nama' => $p->nama, 'perubahan' => $perubahan,
        ]);
    }
}
