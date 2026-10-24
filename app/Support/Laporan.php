<?php

namespace App\Support;

/**
 * Definisi 5 laporan. Setiap laporan berbentuk tabel generik:
 *   cols  : [['h' => judul, 'r' => rata kanan?]]
 *   rows  : [['k' => jenis baris ('' | 'g' grup | 'b' subtotal | 'tt' total), 'c' => [sel...]]]
 *   tfoot : baris jumlah (opsional)
 * Jadi satu view (rekap/_tabel) dan satu export CSV melayani semuanya.
 */
class Laporan
{
    public const DAFTAR = [
        'bulanan' => ['Rekap gaji bulanan', 'Ringkasan penghasilan, potongan, dan gaji bersih per jenis personel.'],
        'penerimaan' => ['Daftar penerimaan gaji', 'Nama, NRP/NIP, dan gaji bersih setiap personel, dengan kolom tanda tangan.'],
        'potongan' => ['Rekap potongan', 'Rincian iuran pensiun, BPJS, PPh 21, dan potongan lain per personel.'],
        'pph' => ['Rekap PPh 21', 'Pajak yang dipotong per personel untuk keperluan pelaporan.'],
        'log' => ['Log perubahan & verifikasi', 'Semua selisih yang ditemukan beserta keputusan Juru Bayar.'],
    ];

    private static function sel(?int $v): string
    {
        return $v ? Fmt::rp($v) : '—';
    }

    private static function potongan(array $r, string $label): int
    {
        foreach ($r['ded'] as [$l, $v]) {
            if ($l === $label) return $v;
        }

        return 0;
    }

    public static function build(string $id): ?array
    {
        if (! isset(self::DAFTAR[$id])) return null;
        [$title, $desc] = self::DAFTAR[$id];
        $rows = [];
        $tfoot = null;
        $paged = true;

        if ($id === 'bulanan') {
            $paged = false;
            $g = Demo::agg();
            $T = $g['A']['TNI'];
            $N = $g['A']['PNS'];
            $cols = [['h' => 'Komponen (Rp)'], ['h' => 'TNI · '.$T['n'].' orang', 'r' => 1], ['h' => 'PNS · '.$N['n'].' orang', 'r' => 1], ['h' => 'Total · '.($T['n'] + $N['n']).' orang', 'r' => 1]];
            $rows[] = ['k' => 'g', 'c' => ['PENGHASILAN']];
            foreach ($g['earn'] as $l) {
                $a = $T['earn'][$l] ?? 0; $b = $N['earn'][$l] ?? 0;
                $rows[] = ['k' => '', 'c' => [$l, self::sel($a), self::sel($b), self::sel($a + $b)]];
            }
            $rows[] = ['k' => 'b', 'c' => ['Jumlah penghasilan', Fmt::rp($T['kotor']), Fmt::rp($N['kotor']), Fmt::rp($T['kotor'] + $N['kotor'])]];
            $rows[] = ['k' => 'g', 'c' => ['POTONGAN']];
            foreach ($g['ded'] as $l) {
                $a = $T['ded'][$l] ?? 0; $b = $N['ded'][$l] ?? 0;
                $rows[] = ['k' => '', 'c' => [$l, self::sel($a), self::sel($b), self::sel($a + $b)]];
            }
            $rows[] = ['k' => 'b', 'c' => ['Jumlah potongan', Fmt::rp($T['pot']), Fmt::rp($N['pot']), Fmt::rp($T['pot'] + $N['pot'])]];
            $rows[] = ['k' => 'tt', 'c' => ['Diterima bersih', Fmt::rp($T['bersih']), Fmt::rp($N['bersih']), Fmt::rp($T['bersih'] + $N['bersih'])]];
        } elseif ($id === 'penerimaan') {
            $cols = [['h' => 'No.', 'r' => 1], ['h' => 'Nama'], ['h' => 'NRP / NIP'], ['h' => 'Jenis'], ['h' => 'Gaji bersih (Rp)', 'r' => 1], ['h' => 'Tanda tangan']];
            $sum = 0;
            foreach (Demo::all() as $r) {
                $sum += $r['bersih'];
                $rows[] = ['k' => '', 'c' => [(string) $r['n'], $r['nama'], $r['nrp'], $r['jenis'], Fmt::rp($r['bersih']), '']];
            }
            $tfoot = ['', 'Jumlah seluruh personel', '', '', Fmt::rp($sum), ''];
        } elseif ($id === 'potongan') {
            $L = ['Iuran pensiun & THT', 'Iuran BPJS Kesehatan', 'PPh 21', 'Sewa rumah dinas', 'Koperasi'];
            $cols = [['h' => 'Nama'], ['h' => 'Jenis']];
            foreach ($L as $l) $cols[] = ['h' => $l, 'r' => 1];
            $cols[] = ['h' => 'Jumlah', 'r' => 1];
            $tot = array_fill(0, 6, 0);
            foreach (Demo::all() as $r) {
                $c = [$r['nama'], $r['jenis']];
                foreach ($L as $i => $l) {
                    $v = self::potongan($r, $l);
                    $tot[$i] += $v;
                    $c[] = self::sel($v);
                }
                $tot[5] += $r['pot'];
                $c[] = Fmt::rp($r['pot']);
                $rows[] = ['k' => '', 'c' => $c];
            }
            $tfoot = array_merge(['Jumlah seluruh personel', ''], array_map([Fmt::class, 'rp'], $tot));
        } elseif ($id === 'pph') {
            $cols = [['h' => 'Nama'], ['h' => 'NRP / NIP'], ['h' => 'Jenis'], ['h' => 'PPh 21 (Rp)', 'r' => 1]];
            $sum = 0;
            foreach (Demo::all() as $r) {
                $v = self::potongan($r, 'PPh 21');
                $sum += $v;
                $rows[] = ['k' => '', 'c' => [$r['nama'], $r['nrp'], $r['jenis'], Fmt::rp($v)]];
            }
            $tfoot = ['Jumlah seluruh personel', '', '', Fmt::rp($sum)];
        } else {
            $cols = [['h' => 'Personel'], ['h' => 'Jenis'], ['h' => 'Perubahan / masalah'], ['h' => 'Keputusan']];
            $proses = session('proses', []);
            foreach (Demo::tidakCocok() as $m) {
                $rows[] = ['k' => '', 'c' => [$m['nama'].' ('.$m['pangkat'].')', $m['jenis'], $m['masalah'],
                    ! empty($proses['fixed'][$m['key']]) ? 'Selesai: '.$m['aksi'] : 'Belum diselesaikan']];
            }
            foreach (Demo::selisih() as $s) {
                $c = $proses['choice'][$s['key']] ?? null;
                $rows[] = ['k' => '', 'c' => [$s['nama'].' ('.$s['pangkat'].')', $s['jenis'], $s['komponen'].': '.$s['lama'].' → '.$s['baru'],
                    $c ? ($c === 'baru' ? 'Pakai softfile' : 'Pakai angka lama') : 'Belum diputuskan']];
            }
        }

        return compact('id', 'title', 'desc', 'cols', 'rows', 'tfoot', 'paged');
    }

    /** Isi berkas CSV (pemisah ";" dan BOM agar terbuka rapi di Excel Indonesia). */
    public static function csv(array $rep): string
    {
        $cv = function ($v) {
            $v = (string) $v;
            if (preg_match('/^[−-]?\d{1,3}(\.\d{3})+$/u', $v)) $v = str_replace(['.', '−'], ['', '-'], $v);

            return preg_match('/[";\n]/', $v) ? '"'.str_replace('"', '""', $v).'"' : $v;
        };
        $lines = [implode(';', array_map(fn ($c) => $cv($c['h']), $rep['cols']))];
        foreach ($rep['rows'] as $r) {
            $lines[] = $r['k'] === 'g' ? $cv($r['c'][0]) : implode(';', array_map($cv, $r['c']));
        }
        if ($rep['tfoot']) $lines[] = implode(';', array_map($cv, $rep['tfoot']));

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }
}
