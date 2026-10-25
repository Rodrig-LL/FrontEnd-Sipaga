<?php

namespace App\Support;

use App\Models\ProsesGaji;
use Illuminate\Support\Collection;

/**
 * Sumber data halaman Dashboard, Slip, dan Rekap: hasil proses gaji yang sudah selesai (hasil.json dari Python).
 * Susunannya mengikuti sipaga.js di aplikasi Python (siapkan(), agg()), supaya angkanya sama di kedua tempat.
 * Menggantikan Demo. Yang dipakai = proses yang dipilih di pemilih periode, atau yang terbaru.
 */
class DataGaji
{
    /** Nama komponen yang tidak ambigu ditulis lengkap; sisanya memakai judul kolom DPP apa adanya. */
    private const NAMA = [
        'g_pokok' => 'Gaji pokok', 't_istri' => 'Tunjangan istri / suami', 't_anak' => 'Tunjangan anak', 't_struktural' => 'Tunjangan struktural',
        't_fungsional' => 'Tunjangan fungsional', 't_umum' => 'Tunjangan umum', 't_beras' => 'Tunjangan pangan / beras', 't_lainnya' => 'Tunjangan lainnya',
        't_pph' => 'Tunjangan PPh', 'pembulatan' => 'Pembulatan', 'lauk_pauk' => 'Uang lauk pauk',
        'p_beras' => 'Potongan beras', 'p_pensiun' => 'Iuran pensiun', 'p_bpjs' => 'Iuran BPJS', 'p_tht' => 'Iuran THT', 'p_bpjs_lain' => 'BPJS lain',
        'p_sewa_rumah' => 'Sewa rumah', 'p_utang' => 'Utang', 'p_pph21' => 'PPh Pasal 21', 'p_pengembalian' => 'Pengembalian', 'p_tgr' => 'TGR',
        'p_lainnya' => 'Potongan lainnya',
    ];

    private static ?array $cache = null;

    private static ?Collection $proses = null;

    /** Dipakai tes (dan setelah ada proses baru dalam satu permintaan). */
    public static function lupakan(): void
    {
        self::$cache = self::$proses = null;
    }

    /** Proses yang sudah selesai, terbaru dulu. */
    public static function daftarProses(): Collection
    {
        return self::$proses ??= ProsesGaji::where('status', 'selesai')->latest('id')->get();
    }

    public static function aktif(): ?ProsesGaji
    {
        $semua = self::daftarProses();

        return $semua->firstWhere('id', (int) session('proses_id')) ?? $semua->first();
    }

    public static function pilih(int $id): bool
    {
        if (! self::daftarProses()->contains('id', $id)) return false;
        session(['proses_id' => $id]);
        self::$cache = null;

        return true;
    }

    public static function ada(): bool
    {
        return self::data() !== null;
    }

    /** "NOVEMBER 2026" -> "November 2026". */
    public static function bulan(): string
    {
        $b = self::data()['hasil']['bulan'] ?? '';

        return $b !== '' ? mb_convert_case($b, MB_CASE_TITLE) : 'bulan tidak terbaca';
    }

    public static function proses(): ?ProsesGaji
    {
        return self::data()['proses'] ?? null;
    }

    public static function hasil(): array
    {
        return self::data()['hasil'] ?? [];
    }

    /** Slip per anggota, kunci = nomor urut (n). */
    public static function slip(): array
    {
        return self::data()['slip'] ?? [];
    }

    public static function find(int $n): ?array
    {
        return self::slip()[$n] ?? null;
    }

    /** Temuan terurut: tingkat, kategori, baris buku. */
    public static function temuan(): array
    {
        return self::data()['temuan'] ?? [];
    }

    /**
     * Jumlah per berkas PDF dan total: ['A' => [file => agregat], 'tot' => agregat], agregat = n, kotor, pot, bersih, k[kolom].
     */
    public static function agg(): array
    {
        return self::data()['agg'] ?? ['A' => [], 'tot' => self::aggBaru()];
    }

    /** Daftar komponen penghasilan menurut urutan slip. */
    public static function kunciPenghasilan(): array
    {
        return array_merge(['g_pokok', 't_istri', 't_anak'], self::hasil()['tunjangan'] ?? [], ['t_pph', 'pembulatan', 'lauk_pauk']);
    }

    public static function kunciPotongan(): array
    {
        return self::hasil()['potongan'] ?? [];
    }

    public static function namaKolom(string $k): string
    {
        return self::NAMA[$k] ?? (self::hasil()['label'][$k] ?? $k);
    }

    /** Saring slip menurut kata kunci (nama/NRP) dan status (siap | verif). */
    public static function saring(array $list, string $q, string $status): array
    {
        $q = mb_strtolower(trim($q));

        return array_values(array_filter($list, function ($r) use ($q, $status) {
            if ($status !== '' && $r['stat'] !== $status) return false;

            return $q === '' || str_contains(mb_strtolower($r['nama']), $q) || str_contains($r['nrp'], preg_replace('/\D/', '', $q) ?: "\0");
        }));
    }

    private static function aggBaru(): array
    {
        return ['n' => 0, 'k' => [], 'kotor' => 0, 'pot' => 0, 'bersih' => 0];
    }

    private static function data(): ?array
    {
        if (self::$cache !== null) return self::$cache ?: null;
        $p = self::aktif();
        $hasil = $p?->hasil();
        if (! $p || ! $hasil) {
            self::$cache = [];

            return null;
        }

        // Temuan, diurutkan, lalu dipetakan ke anggota lewat baris buku atau NRP (INFO tidak ikut menandai slip).
        $urut = ['TINGGI' => 0, 'SEDANG' => 1, 'INFO' => 2];
        $temuan = $hasil['temuan'];
        usort($temuan, fn ($a, $b) => [$urut[$a['tingkat']] ?? 9, $a['kategori'], $a['baris'] ?? 9999] <=> [$urut[$b['tingkat']] ?? 9, $b['kategori'], $b['baris'] ?? 9999]);
        $perBaris = $perNrp = [];
        foreach ($temuan as $t) {
            if ($t['baris'] !== null) $perBaris[$t['baris']][] = $t;
            elseif (($k = preg_replace('/\D/', '', (string) $t['nrp'])) !== '') $perNrp[$k][] = $t;
        }

        self::$cache = ['proses' => $p, 'hasil' => $hasil, 'temuan' => $temuan, 'slip' => [], 'agg' => ['A' => [], 'tot' => self::aggBaru()]];
        $earnKeys = self::kunciPenghasilan();
        $dedKeys = self::kunciPotongan();
        $A = &self::$cache['agg']['A'];
        $tot = &self::$cache['agg']['tot'];
        foreach ($hasil['dpp'] as $f) $A[$f['file']] = self::aggBaru();
        $tambah = function (array &$a, array $r, array $o) use ($hasil) {
            $a['n']++;
            $a['kotor'] += $r['kotor'];
            $a['pot'] += $r['pot'];
            $a['bersih'] += $r['bersih'];
            foreach ($hasil['kolom'] as $k) $a['k'][$k] = ($a['k'][$k] ?? 0) + ($o[$k] ?? 0);
        };

        foreach ($hasil['personel'] as $o) {
            $earn = $ded = [];
            foreach ($earnKeys as $k) if (($o[$k] ?? 0) || $k === 'g_pokok') $earn[] = [self::namaKolom($k), $o[$k] ?? 0];
            foreach ($dedKeys as $k) if ($o[$k] ?? 0) $ded[] = [self::namaKolom($k), $o[$k]];
            $tm = array_values(array_filter(array_merge($o['baris'] !== null ? ($perBaris[$o['baris']] ?? []) : [],
                $perNrp[preg_replace('/\D/', '', (string) $o['nrp'])] ?? []), fn ($t) => $t['tingkat'] !== 'INFO'));
            $pangkat = (string) $o['pangkat'];
            if (($o['korps'] ?? '') !== '' && ! str_ends_with($pangkat, $o['korps'])) $pangkat = trim($pangkat.' '.$o['korps']);
            $r = [
                'n' => $o['n'], 'nama' => $o['nama'] ?: '(nama tidak terbaca)', 'pangkat' => $pangkat, 'nrp' => (string) $o['nrp'],
                'jabatan' => $o['jabatan'] ?? '', 'kawin' => $o['sts_kawin'] ?? '', 'file' => $o['file'], 'halaman' => $o['halaman'], 'no_urut' => $o['no_urut'],
                'stat' => $o['masalah'] || $tm ? 'verif' : 'siap', 'masalah' => $o['masalah'], 'tm' => $tm,
                'earn' => $earn, 'ded' => $ded, 'kotor' => $o['jml_kotor'] + $o['lauk_pauk'], 'pot' => $o['jml_potongan'], 'bersih' => $o['dibayarkan'],
                'baris' => $o['baris'], 'cara' => $o['cara'], 'gaji_lama' => $o['gaji_lama'], 'scan' => $o['scan'], 'k' => array_intersect_key($o, array_flip($hasil['kolom'])),
            ];
            self::$cache['slip'][$o['n']] = $r;
            $A[$o['file']] ??= self::aggBaru();
            $tambah($A[$o['file']], $r, $o);
            $tambah($tot, $r, $o);
        }

        return self::$cache;
    }
}
