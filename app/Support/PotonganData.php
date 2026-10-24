<?php

namespace App\Support;

use App\Models\Personel;
use App\Models\Pinjaman;
use Illuminate\Support\Facades\DB;

/**
 * Jenis potongan satuan dan potret datanya untuk jembatan Python. Daftar jenis harus sama dengan
 * potongan.JENIS di python/potongan.py (kode dipakai di kedua sisi).
 */
class PotonganData
{
    /** kode => [label, kelompok] */
    public const JENIS = [
        'SIMP_WAJIB' => ['Simpanan wajib koperasi', 'koperasi'], 'KOPERASI' => ['Tanggungan Koperasi Ditajenad', 'pinjaman'],
        'LURJA' => ['LURJA', 'pinjaman'], 'BRI_AYANI' => ['BRI A Yani', 'bank'], 'BRI_AA' => ['BRI AA', 'bank'], 'BJB' => ['BJB', 'bank'],
        'BWS' => ['BWS', 'bank'], 'BTN' => ['BTN', 'bank'], 'TWP' => ['TWP', 'tetap'], 'PERSIT_BINUM' => ['Persit Binum', 'tetap'],
        'PERSIT_BINCAB' => ['Persit Bincab', 'tetap'], 'PERSIT_PERSPRA' => ['Persit Perspra', 'tetap'], 'PERSIT_DIASAHPRA' => ['Persit Diasahpra', 'tetap'],
        'PERSIT_BINPNS' => ['Persit Binpns', 'tetap'], 'PERSIT_SIKHIB' => ['Persit Sikhib', 'tetap'], 'PERSIT_MINU' => ['Persit Minu', 'tetap'],
        'PERSIT_IT' => ['Persit IT', 'tetap'], 'KOWAD' => ['Kowad', 'tetap'], 'IURAN_PAMA' => ['Iuran Pama', 'tetap'], 'IURAN_KASI' => ['Iuran Kasi', 'tetap'],
        'SOSIAL_URDAL' => ['Sosial Urdal', 'tetap'], 'PMI' => ['PMI', 'tetap'], 'KORPRI' => ['Korpri', 'tetap'], 'LAIN_LAIN' => ['Lain-lain', 'tetap'],
        'ARISAN' => ['Arisan Persit', 'arisan'],
    ];

    public const PINJAMAN = ['KOPERASI' => 'Koperasi Ditajenad', 'LURJA' => 'LURJA'];

    public const PEMBERI_BATAS = ['KOPERASI', 'LURJA', 'BRI_AYANI', 'BRI_AA', 'BJB', 'BWS', 'BTN'];

    public const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public static function label(string $kode): string
    {
        return self::JENIS[$kode][0] ?? $kode;
    }

    /** Kode yang disimpan di tabel potongan (pinjaman koperasi/LURJA dicatat di tabel pinjaman). */
    public static function kodePotongan(): array
    {
        return array_values(array_diff(array_keys(self::JENIS), array_keys(self::PINJAMAN)));
    }

    public static function periodeSah(?string $p): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $p);
    }

    /** '2026-10' -> 'Oktober 2026' */
    public static function teksPeriode(?string $p): string
    {
        return self::periodeSah($p) ? self::BULAN[(int) substr($p, 5, 2) - 1].' '.substr($p, 0, 4) : '';
    }

    /** Keadaan satu pinjaman pada periode $p: dipotong atau tidak, cicilan ke berapa (padanan potongan.jalan_pada). */
    public static function jalanPada(Pinjaman $pj, string $p): array
    {
        if ($pj->status !== 'aktif') return ['dipotong' => false, 'ke' => null, 'akhir' => false, 'ket' => $pj->status];
        if (! self::periodeSah($p)) return ['dipotong' => true, 'ke' => null, 'akhir' => false, 'ket' => 'bulan proses tidak terbaca'];
        $ke = ((int) substr($p, 0, 4) - (int) substr($pj->mulai, 0, 4)) * 12 + (int) substr($p, 5, 2) - (int) substr($pj->mulai, 5, 2) + 1;
        if ($ke < 1) return ['dipotong' => false, 'ke' => $ke, 'akhir' => false, 'ket' => 'mulai '.mb_strtolower(self::teksPeriode($pj->mulai))];
        if ($pj->tenor === null) return ['dipotong' => true, 'ke' => $ke, 'akhir' => false, 'ket' => 'tenor belum diisi'];
        if ($ke > $pj->tenor) return ['dipotong' => false, 'ke' => $ke, 'akhir' => false, 'ket' => 'selesai'];

        return ['dipotong' => true, 'ke' => $ke, 'akhir' => $ke === $pj->tenor, 'ket' => "cicilan ke-$ke dari {$pj->tenor}"];
    }

    /** Aturan dengan nilai bawaan; sama dengan potongan._aturan(). */
    public static function aturan(): array
    {
        $a = ['sisa_ybs' => 5000, 'pokok' => null, 'arisan' => [],
            'batas' => array_fill_keys(self::PEMBERI_BATAS, ['persen' => null, 'sisa_min' => null, 'tenor_maks' => null])];
        foreach (DB::table('aturan_potongan')->get() as $r) {
            $v = json_decode($r->nilai, true);
            if ($r->kunci === 'batas') {
                foreach (self::PEMBERI_BATAS as $p) $a['batas'][$p] = array_merge($a['batas'][$p], $v[$p] ?? []);
            } else {
                $a[$r->kunci] = $v;
            }
        }

        return $a;
    }

    /** Potret data untuk jembatan Python (padanan potongan.snapshot()). */
    public static function snapshot(): array
    {
        $pot = [];
        foreach (DB::table('potongan')->get() as $r) $pot[$r->personel_id][$r->kode] = (int) $r->nominal;
        $pokok = [];
        foreach (DB::table('pokok')->get() as $r) $pokok[$r->personel_id] = ['personel_id' => $r->personel_id, 'bulan' => $r->bulan, 'nominal' => (int) $r->nominal];

        return [
            'personel' => Personel::orderBy('id')->get()->map->keBaris()->all(),
            'potongan' => (object) $pot,
            'pinjaman' => Pinjaman::orderBy('id')->get()->map(fn (Pinjaman $x) => [
                'id' => $x->id, 'personel_id' => $x->personel_id, 'pemberi' => $x->pemberi, 'cicilan' => (int) $x->cicilan, 'mulai' => $x->mulai,
                'tenor' => $x->tenor, 'tgl_cair' => $x->tgl_cair, 'catatan' => $x->catatan, 'status' => $x->status,
                'dibuat' => (string) $x->created_at, 'diubah' => (string) $x->updated_at,
            ])->all(),
            'pokok' => (object) $pokok,
            'aturan' => self::aturan(),
        ];
    }
}
