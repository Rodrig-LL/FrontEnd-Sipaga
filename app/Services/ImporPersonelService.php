<?php

namespace App\Services;

use App\Models\ImporPersonel;
use App\Models\Personel;
use App\Models\Pinjaman;
use App\Models\Riwayat;
use App\Models\User;
use App\Support\PotonganData;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Impor Data Personel dari Excel dengan pratinjau. Python (jembatan_personel.py) hanya MEMBACA berkas
 * dan menyusun rencana; Laravel yang menerapkannya, dalam satu transaksi dengan riwayat per baris
 * (padanan personel_db.terapkan()). Berkas yang diunggah dibuang begitu rencana jadi.
 */
class ImporPersonelService
{
    private const DARI_BUKU = ['bank', 'rekening', 'nama_rekening'];

    /** @param  UploadedFile[]  $berkas */
    public function pratinjau(string $jenis, array $berkas, User $oleh): ImporPersonel
    {
        $disk = Storage::disk('local');
        $dir = 'impor/'.Str::uuid();
        $namaAsli = $paths = [];
        try {
            foreach ($berkas as $i => $f) {
                $nama = $i.'_'.preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($f->getClientOriginalName()));
                $f->storeAs($dir, $nama, 'local');
                $paths[] = $disk->path($dir.'/'.$nama);
                $namaAsli[] = $f->getClientOriginalName();
            }
            // Python membaca nama berkas (bank dari nama pembukuan), jadi pakai nama aslinya tanpa awalan urutan.
            foreach ($paths as $i => $p) {
                $baru = dirname($p).'/'.preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($namaAsli[$i]));
                if ($baru !== $p) {
                    if (file_exists($baru)) throw new ImporGagal("Ada dua berkas bernama {$namaAsli[$i]}. Ganti nama salah satunya.");
                    rename($p, $baru);
                    $paths[$i] = $baru;
                }
            }
            $disk->put($dir.'/tugas.json', json_encode([
                'mode' => $jenis, 'berkas' => $paths, 'hasil_json' => $disk->path($dir.'/hasil.json'),
                'personel' => Personel::orderBy('id')->get()->map->keBaris()->all(),
                'potongan' => in_array($jenis, ['potongan', 'arisan'], true) ? PotonganData::snapshot() : null,
            ], JSON_UNESCAPED_UNICODE));

            $r = Process::path(base_path('python'))->timeout(300)
                ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONDONTWRITEBYTECODE' => '1'])
                ->run([config('sipaga.python'), 'jembatan_personel.py', $disk->path($dir.'/tugas.json')]);

            $hasil = $disk->exists($dir.'/hasil.json') ? json_decode($disk->get($dir.'/hasil.json'), true) : null;
            if ($hasil === null) {
                throw new ImporGagal('Python berhenti tanpa hasil'.($r->exitCode() ? " (kode {$r->exitCode()})" : '').'. Periksa pemasangan Python dan python/requirements.txt. '
                    .mb_substr(trim($r->errorOutput()), -300));
            }
            if (! ($hasil['ok'] ?? false)) throw new ImporGagal($hasil['galat'] ?? 'Berkas tidak bisa dibaca.');

            return ImporPersonel::create(['user_id' => $oleh->id, 'jenis' => $jenis, 'berkas' => $namaAsli,
                'rencana' => $hasil['rencana'], 'sidik' => $this->sidik()]);
        } finally {
            $disk->deleteDirectory($dir);          // data pribadi: jangan disimpan lebih lama dari perlu
        }
    }

    /**
     * Terapkan rencana. Ditolak bila Data Personel berubah sejak pratinjau (padanan versi di Python).
     *
     * @return array{tambah: int, ubah: int, tandai: int, pinjaman: int, berhenti: int}
     */
    public function terapkan(ImporPersonel $impor, User $oleh): array
    {
        return DB::transaction(function () use ($impor, $oleh) {
            $impor = ImporPersonel::lockForUpdate()->findOrFail($impor->id);
            if ($impor->status !== 'pratinjau') throw new ImporGagal('Impor ini sudah diterapkan.');
            if ($impor->sidik !== $this->sidik()) {
                throw new ImporGagal('Data personel berubah sejak pratinjau dibuat (mungkin oleh pengguna lain). Buat pratinjau ulang, lalu terapkan lagi.');
            }

            $r = $impor->rencana;
            $n = ['tambah' => 0, 'ubah' => 0, 'tandai' => 0, 'pinjaman' => 0, 'berhenti' => 0];
            $catat = fn (string $aksi, ?Personel $p, string $nrp, string $nama, array $perubahan) => Riwayat::create([
                'user_id' => $oleh->id, 'operator' => $oleh->name, 'aksi' => $aksi, 'personel_id' => $p?->id,
                'nrp' => $nrp, 'nama' => $nama, 'perubahan' => $perubahan,
            ]);

            if ($impor->jenis === 'nominatif') {
                foreach ($r['tambah'] as $b) {
                    $p = Personel::create($this->nilai($b) + ['status' => 'aktif']);
                    $catat('impor: tambah', $p, $p->nrp, $p->nama, collect(['jenis', 'nama', 'pangkat', 'nrp_asli', 'jabatan', 'ket', 'seksi', 'tgl_lahir'])
                        ->filter(fn ($k) => ($b[$k] ?? '') !== '')->mapWithKeys(fn ($k) => [$k => ['', (string) $b[$k]]])->all());
                    $n['tambah']++;
                }
                foreach ($r['hilang'] as $x) {
                    $p = Personel::find($x['id']);
                    if (! $p || $p->tandai === $x['tandai']) continue;
                    $lama = $p->tandai;
                    $p->update(['tandai' => mb_substr($x['tandai'], 0, Personel::PANJANG['tandai'])]);
                    $catat('impor: ditandai', $p, $p->nrp, $p->nama, ['tandai' => [$lama, $x['tandai']]]);
                    $n['tandai']++;
                }
            }
            $rp = fn ($v) => PotonganService::rp($v);
            if ($impor->jenis === 'potongan') {
                foreach ($r['ubah'] as $x) {
                    if (! $p = Personel::find($x['id'])) continue;
                    foreach ($x['potongan'] as $kode => $nominal) PotonganService::setPot($p->id, $kode, (int) $nominal);
                    $catat('impor: potongan', $p, $p->nrp, $p->nama, $x['perubahan']);
                    $n['ubah']++;
                }
                foreach ($r['pinjaman_baru'] as $x) {
                    if (! $p = Personel::find($x['id'])) continue;
                    Pinjaman::create(['personel_id' => $p->id, 'pemberi' => $x['pemberi'], 'cicilan' => $x['cicilan'], 'mulai' => $x['mulai'], 'tenor' => null,
                        'tgl_cair' => '', 'status' => 'aktif', 'catatan' => mb_substr("Diimpor dari {$x['asal']}: pinjaman berjalan, sisa tenor belum diketahui.".($x['tandai'] ? ' '.$x['tandai'] : ''), 0, 500)]);
                    $catat('impor: pinjaman', $p, $p->nrp, $p->nama, ['Pemberi' => ['', PotonganData::label($x['pemberi'])], 'Cicilan/bulan' => ['', $rp($x['cicilan'])], 'Tenor' => ['', '(belum diisi)']]);
                    $n['pinjaman']++;
                }
            } elseif ($impor->jenis === 'arisan') {
                foreach ($r['ubah'] as $x) {
                    if (! $p = Personel::find($x['id'])) continue;
                    PotonganService::setPot($p->id, 'ARISAN', (int) $x['baru']);
                    $catat('impor: arisan', $p, $p->nrp, $p->nama, ['Arisan Persit' => [$rp($x['lama']), $rp($x['baru'])]]);
                    $n['ubah']++;
                }
                foreach ($r['berhenti'] as $x) {
                    if (! $p = Personel::find($x['id'])) continue;
                    PotonganService::setPot($p->id, 'ARISAN', 0);
                    $catat('impor: arisan', $p, $p->nrp, $p->nama, ['Arisan Persit' => [$rp($x['lama']), '']]);
                    $n['berhenti']++;
                }
                $meta = PotonganData::aturan()['arisan'] ?: [];
                foreach ($r['meta'] as $bank => $m) $meta[$bank] = $m + ['diimpor' => now()->format('Y-m-d H:i:s')];
                DB::table('aturan_potongan')->updateOrInsert(['kunci' => 'arisan'], ['nilai' => json_encode($meta)]);
            }
            foreach (in_array($impor->jenis, ['nominatif', 'bank'], true) ? $r['ubah'] : [] as $x) {
                $p = Personel::find($x['id']);
                if (! $p) continue;
                $p->update($this->nilai(array_map(fn ($pair) => $pair[1], $x['perubahan'])));
                $catat($impor->jenis === 'bank' ? 'impor: bank' : 'impor: ubah', $p, $p->nrp, $p->nama, $x['perubahan']);
                $n['ubah']++;
            }
            $catat('impor', null, '', '', ['berkas' => $impor->berkas, 'jenis' => $impor->jenis] + $n);
            $impor->update(['status' => 'diterapkan', 'hasil' => $n, 'diterapkan_at' => now()]);

            return $n;
        });
    }

    /** Kondisi Data Personel: setiap penulisan lewat layanan mencatat riwayat, jadi id riwayat terakhir + jumlah cukup sebagai sidik jari. */
    public function sidik(): string
    {
        return (Riwayat::max('id') ?? 0).'|'.Personel::count();
    }

    /** Nilai dari rencana Python -> atribut model: dipotong sesuai kolom, tanggal kosong = null, NRP angka mengikuti nrp_asli. */
    private function nilai(array $v): array
    {
        $o = [];
        foreach ($v as $k => $x) {
            if (! in_array($k, ['jenis', 'nama', 'pangkat', 'nrp_asli', 'jabatan', 'ket', 'seksi', 'tgl_lahir', 'bank', 'rekening', 'nama_rekening', 'tandai'], true)) continue;
            $x = (string) ($x ?? '');
            $o[$k] = $k === 'tgl_lahir' ? ($x === '' ? null : $x) : mb_substr($x, 0, Personel::PANJANG[$k] ?? 255);
        }
        if (isset($o['nrp_asli'])) $o['nrp'] = mb_substr(preg_replace('/\D/', '', $o['nrp_asli']), 0, Personel::PANJANG['nrp']);

        return $o;
    }
}
