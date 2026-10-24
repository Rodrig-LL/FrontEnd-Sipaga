<?php

namespace App\Services;

use App\Models\Personel;
use App\Models\ProsesGaji;
use App\Support\PotonganData;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Menjalankan python/jembatan.py untuk satu ProsesGaji: tulis tugas.json, jalankan Python,
 * baca hasil.json. Logika gaji seluruhnya ada di Python; di sini hanya serah-terima berkas.
 */
class JembatanPython
{
    public function jalankan(ProsesGaji $p): void
    {
        $disk = Storage::disk('local');
        $p->update(['status' => 'berjalan', 'mulai_at' => now(), 'galat' => null]);

        $disk->put($p->folder('tugas.json'), json_encode([
            'pdf' => array_map(fn ($n) => $p->path('masuk/'.$n), $p->berkas['pdf']),
            'buku' => $p->path('masuk/'.$p->berkas['buku']),
            'keluar' => $p->path('hasil'),
            'bulan' => $p->bulan_paksa ?: null,
            'personel' => $this->acuan(),
            'potongan' => PotonganData::snapshot(),
            'hasil_json' => $p->path('hasil.json'),
        ], JSON_UNESCAPED_UNICODE));

        $r = Process::path(base_path('python'))
            ->timeout(config('sipaga.python_timeout'))
            ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONDONTWRITEBYTECODE' => '1'])
            ->run([config('sipaga.python'), 'jembatan.py', $p->path('tugas.json')]);

        $hasil = $p->hasil();
        if ($hasil === null) {
            // Python mati sebelum sempat menulis hasil (mis. modul belum terpasang, kehabisan waktu).
            $this->gagal($p, 'Proses Python berhenti tanpa hasil'.($r->exitCode() ? " (kode {$r->exitCode()})" : '').'. '
                .'Periksa pemasangan Python dan python/requirements.txt. '.mb_substr(trim($r->errorOutput()), -400));

            return;
        }
        if (! ($hasil['ok'] ?? false)) {
            $this->gagal($p, $hasil['galat'] ?? 'Proses gagal.');

            return;
        }

        $stat = $hasil['statistik'];
        $p->update([
            'status' => 'selesai', 'selesai_at' => now(), 'bulan' => $hasil['bulan'] ?? '',
            'ringkasan' => [
                'tingkat' => $hasil['tingkat'], 'anggota' => count($hasil['personel']),
                'total_dpp' => $stat['total_dpp_cocok'], 'rekonsiliasi_cocok' => $hasil['rekonsiliasi']['cocok'],
                'gaji_berubah' => $stat['gaji_berubah'], 'dpp_tanpa_buku' => $stat['dpp_tanpa_buku'],
                'pencocokan' => $hasil['pencocokan']['hitung'] ?? null,
            ],
        ]);
    }

    public function gagal(ProsesGaji $p, string $pesan): void
    {
        $p->update(['status' => 'gagal', 'selesai_at' => now(), 'galat' => $pesan]);
    }

    /** Acuan pencocokan DPP dari tabel personel (bentuk yang sama dengan personel_db.acuan("TNI")). */
    public function acuan(): array
    {
        $orang = Personel::where('jenis', 'TNI')->orderBy('id')->get()->map->keBaris()->all();

        return ['orang' => $orang, 'jenis' => 'TNI', 'diubah' => (string) Personel::max('updated_at'),
            'lain' => Personel::aktif()->where('jenis', 'PNS')->count()];
    }
}
