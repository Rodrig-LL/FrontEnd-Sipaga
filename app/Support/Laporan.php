<?php

namespace App\Support;

/**
 * Definisi 5 laporan dari data DPP hasil proses gaji (DataGaji); isinya sama dengan sipaga.js di aplikasi Python.
 * Setiap laporan berbentuk tabel generik:
 *   cols  : [['h' => judul, 'r' => rata kanan?, 't' => teks (NRP)?]]
 *   rows  : [['k' => jenis baris ('' | 'g' grup | 'b' subtotal | 'tt' total), 'c' => [sel...]]]
 *   tfoot : baris jumlah (opsional)
 * Jadi satu view (partials/tabel-laporan) dan satu export CSV melayani semuanya.
 */
class Laporan
{
    public const DAFTAR = [
        'bulanan' => ['Rekap gaji bulanan', 'Jumlah tiap komponen penghasilan dan potongan menurut DPP, per berkas PDF.'],
        'penerimaan' => ['Daftar penerimaan gaji', 'Nama, NRP, dan jumlah dibayarkan setiap anggota, dengan kolom tanda tangan.'],
        'potongan' => ['Rekap potongan', 'Rincian potongan di DPP per anggota. Potongan satuan tidak termasuk.'],
        'pph' => ['Rekap PPh 21', 'PPh Pasal 21 per anggota menurut DPP.'],
        'temuan' => ['Daftar temuan', 'Semua temuan pemeriksaan, sama dengan sheet TEMUAN di laporan validasi.'],
    ];

    private static function sel(int $v): string
    {
        return $v ? Fmt::rp($v) : '—';
    }

    public static function build(string $id): ?array
    {
        if (! isset(self::DAFTAR[$id]) || ! DataGaji::ada()) return null;
        [$title, $desc] = self::DAFTAR[$id];
        $rows = [];
        $tfoot = null;
        $paged = true;
        $slip = array_values(DataGaji::slip());
        $g = DataGaji::agg();
        $tot = $g['tot'];

        if ($id === 'bulanan') {
            $paged = false;
            $files = count($g['A']) > 1 && count($g['A']) <= 5 ? array_keys($g['A']) : [];
            $cols = [['h' => 'Komponen (Rp)']];
            foreach ($files as $f) $cols[] = ['h' => $f.' · '.$g['A'][$f]['n'].' orang', 'r' => 1];
            $cols[] = ['h' => 'Total · '.$tot['n'].' orang', 'r' => 1];
            $baris = function (string $k, string $label, callable $fn, bool $nol = false) use (&$rows, $files, $g, $tot) {
                $c = [$label];
                foreach ($files as $f) $c[] = $nol ? Fmt::rp($fn($g['A'][$f])) : self::sel($fn($g['A'][$f]));
                $c[] = $nol ? Fmt::rp($fn($tot)) : self::sel($fn($tot));
                $rows[] = ['k' => $k, 'c' => $c];
            };
            $rows[] = ['k' => 'g', 'c' => ['PENGHASILAN']];
            foreach (DataGaji::kunciPenghasilan() as $k) if ($tot['k'][$k] ?? 0) $baris('', DataGaji::namaKolom($k), fn ($a) => $a['k'][$k] ?? 0);
            $baris('b', 'Jumlah penghasilan', fn ($a) => $a['kotor'], true);
            $rows[] = ['k' => 'g', 'c' => ['POTONGAN']];
            foreach (DataGaji::kunciPotongan() as $k) if ($tot['k'][$k] ?? 0) $baris('', DataGaji::namaKolom($k), fn ($a) => $a['k'][$k] ?? 0);
            $baris('b', 'Jumlah potongan', fn ($a) => $a['pot'], true);
            $baris('tt', 'Jumlah dibayarkan', fn ($a) => $a['bersih'], true);
        } elseif ($id === 'penerimaan') {
            $cols = [['h' => 'No.', 'r' => 1], ['h' => 'Nama'], ['h' => 'Pangkat'], ['h' => 'NRP', 't' => 1], ['h' => 'Dibayarkan (Rp)', 'r' => 1], ['h' => 'Tanda tangan']];
            foreach ($slip as $r) $rows[] = ['k' => '', 'c' => [(string) $r['n'], $r['nama'], $r['pangkat'], $r['nrp'], Fmt::rp($r['bersih']), '']];
            $tfoot = ['', 'Jumlah seluruh anggota', '', '', Fmt::rp($tot['bersih']), ''];
        } elseif ($id === 'potongan') {
            $ada = array_values(array_filter(DataGaji::kunciPotongan(), fn ($k) => $tot['k'][$k] ?? 0));
            $cols = [['h' => 'Nama'], ['h' => 'NRP', 't' => 1]];
            foreach ($ada as $k) $cols[] = ['h' => DataGaji::namaKolom($k), 'r' => 1];
            $cols[] = ['h' => 'Jumlah', 'r' => 1];
            foreach ($slip as $r) $rows[] = ['k' => '', 'c' => array_merge([$r['nama'], $r['nrp']], array_map(fn ($k) => self::sel($r['k'][$k] ?? 0), $ada), [Fmt::rp($r['pot'])])];
            $tfoot = array_merge(['Jumlah seluruh anggota', ''], array_map(fn ($k) => Fmt::rp($tot['k'][$k]), $ada), [Fmt::rp($tot['pot'])]);
        } elseif ($id === 'pph') {
            $cols = [['h' => 'Nama'], ['h' => 'Pangkat'], ['h' => 'NRP', 't' => 1], ['h' => 'PPh Pasal 21 (Rp)', 'r' => 1]];
            foreach ($slip as $r) $rows[] = ['k' => '', 'c' => [$r['nama'], $r['pangkat'], $r['nrp'], Fmt::rp($r['k']['p_pph21'] ?? 0)]];
            $tfoot = ['Jumlah seluruh anggota', '', '', Fmt::rp($tot['k']['p_pph21'] ?? 0)];
        } else {
            $cols = [['h' => 'Tingkat'], ['h' => 'Kategori'], ['h' => 'Baris buku', 'r' => 1], ['h' => 'NRP', 't' => 1], ['h' => 'Nama'], ['h' => 'Keterangan']];
            foreach (DataGaji::temuan() as $t) $rows[] = ['k' => '', 'c' => [$t['tingkat'], $t['kategori'], $t['baris'] === null ? '' : (string) $t['baris'], (string) $t['nrp'], (string) $t['nama'], $t['detail']]];
        }

        return compact('id', 'title', 'desc', 'cols', 'rows', 'tfoot', 'paged');
    }

    /** Isi berkas CSV (pemisah ";" dan BOM agar terbuka rapi di Excel Indonesia). Kolom NRP ditulis ="..." supaya Excel tidak membuang nol di depan. */
    public static function csv(array $rep): string
    {
        $cv = function ($v, $kol = null) {
            $v = (string) $v;
            if (! empty($kol['t']) && $v !== '') return '="'.$v.'"';
            if (preg_match('/^[−-]?\d{1,3}(\.\d{3})+$/u', $v)) $v = str_replace(['.', '−'], ['', '-'], $v);

            return $v;
        };
        $q = function ($v, $kol = null) use ($cv) {
            $v = $cv($v, $kol);

            return preg_match('/[";\n]/', $v) ? '"'.str_replace('"', '""', $v).'"' : $v;
        };
        $baris = fn (array $c) => implode(';', array_map(fn ($v, $i) => $q($v, $rep['cols'][$i] ?? null), $c, array_keys($c)));
        $lines = [implode(';', array_map(fn ($c) => $q($c['h']), $rep['cols']))];
        foreach ($rep['rows'] as $r) $lines[] = $r['k'] === 'g' ? $q($r['c'][0]) : $baris($r['c']);
        if ($rep['tfoot']) $lines[] = $baris($rep['tfoot']);

        return "\xEF\xBB\xBF".implode("\r\n", $lines);
    }
}
