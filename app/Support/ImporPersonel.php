<?php

namespace App\Support;

/** Membaca & memeriksa berkas CSV daftar personel sebelum ditambahkan. */
class ImporPersonel
{
    public const TEMPLAT = "nama;jenis;pangkat;nrp;bagian;anak\r\nContoh Satu;TNI;Serka;31000001;Bag. Umum;2\r\nContoh Dua;PNS;III/b;199001012015;Bag. Keuangan;0\r\n";

    public const CONTOH = "nama;jenis;pangkat;nrp;bagian;anak\nHasan Basri;TNI;Serka;31778801;Bag. Umum;2\nLilis Suryani;PNS;III/b;199205142015;Bag. Keuangan;0\nEko Prabowo;TNI;Kopda;31778802;Bag. Logistik;1\nTono;TNI;Jenderal;31778803;Bag. Hukum;0\nMira Lestari;PNS;II/c;199311202016;Bag. Personel;1\nAhmad Fauzi;TNI;Mayor Inf;11•••2214;Bag. Personel;2\n";

    private static function norm(string $s): string
    {
        return preg_replace('/\s+/u', '', mb_strtolower($s));
    }

    /** "Penata Muda Tk. I · III/b" bisa ditulis lengkap, "Penata Muda Tk. I", atau "III/b". */
    private static function cariPangkat(string $jenis, string $in): ?string
    {
        $k = self::norm($in);
        if ($k === '') return null;
        foreach (Demo::pangkat($jenis) as $full) {
            $bagian = explode(' · ', $full);
            if (self::norm($full) === $k || self::norm($bagian[0]) === $k || (isset($bagian[1]) && self::norm($bagian[1]) === $k)) {
                return $full;
            }
        }

        return null;
    }

    private static function parseCsv(string $isi): array
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
        $baris1 = strtok($isi, "\n") ?: '';
        $d = substr_count($baris1, ';') >= substr_count($baris1, ',') ? ';' : ',';
        $h = fopen('php://temp', 'r+');
        fwrite($h, $isi);
        rewind($h);
        $out = [];
        while (($r = fgetcsv($h, 0, $d)) !== false) {
            if (trim(implode('', $r)) !== '') $out[] = $r;
        }
        fclose($h);

        return $out;
    }

    /** @return array{nama: string, galat?: string, rows?: array} */
    public static function baca(string $nama, string $isi): array
    {
        $t = self::parseCsv($isi);
        if (count($t) < 2) return ['nama' => $nama, 'galat' => 'Berkas kosong atau hanya berisi judul kolom.'];

        $h = array_map(fn ($x) => preg_replace('/[^a-z]/', '', mb_strtolower(trim($x))), $t[0]);
        $ix = function (array $names) use ($h) {
            foreach ($names as $n) {
                $p = array_search($n, $h, true);
                if ($p !== false) return $p;
            }

            return -1;
        };
        $c = ['nama' => $ix(['nama']), 'jenis' => $ix(['jenis']), 'pangkat' => $ix(['pangkat', 'pangkatgol', 'pangkatgolongan', 'golongan']),
            'nrp' => $ix(['nrp', 'nip', 'nrpnip']), 'bagian' => $ix(['bagian']), 'anak' => $ix(['anak', 'jumlahanak'])];
        $kurang = array_keys(array_filter(['nama' => $c['nama'], 'jenis' => $c['jenis'], 'pangkat' => $c['pangkat'], 'nrp' => $c['nrp']], fn ($v) => $v < 0));
        if ($kurang) {
            return ['nama' => $nama, 'galat' => 'Kolom '.implode(', ', $kurang).' tidak ditemukan. Pakai templat dari tombol “Unduh templat”.'];
        }

        $ada = [];
        foreach (Demo::all() as $r) $ada[$r['nrp']] = $r['nama'];

        $rows = [];
        foreach (array_slice($t, 1) as $i => $r) {
            $g = fn ($k) => $c[$k] < 0 ? '' : trim((string) ($r[$c[$k]] ?? ''));
            $o = ['baris' => $i + 2, 'nama' => $g('nama'), 'jenis' => mb_strtoupper($g('jenis')), 'pangkat_in' => $g('pangkat'),
                'nrp' => $g('nrp'), 'bagian_in' => $g('bagian'), 'anak_in' => $g('anak'), 'masalah' => ''];
            $pk = in_array($o['jenis'], ['TNI', 'PNS'], true) ? self::cariPangkat($o['jenis'], $o['pangkat_in']) : null;
            if ($o['nama'] === '') $o['masalah'] = 'Nama kosong.';
            elseif (! in_array($o['jenis'], ['TNI', 'PNS'], true)) $o['masalah'] = 'Jenis harus TNI atau PNS.';
            elseif (! $pk) $o['masalah'] = 'Pangkat / golongan tidak dikenal.';
            elseif ($o['nrp'] === '') $o['masalah'] = 'NRP / NIP kosong.';
            elseif (isset($ada[$o['nrp']])) $o['masalah'] = 'NRP/NIP sudah ada ('.$ada[$o['nrp']].').';
            elseif ($o['anak_in'] !== '' && ! preg_match('/^[0-5]$/', $o['anak_in'])) $o['masalah'] = 'Jumlah anak harus 0–5.';

            if ($o['masalah'] === '') {
                $ada[$o['nrp']] = $o['nama'];
                $o['pangkat'] = $pk;
                $o['anak'] = $o['anak_in'] === '' ? 0 : (int) $o['anak_in'];
                $o['bagian'] = 'Bag. Umum';
                foreach (Demo::BAGIAN as $b) {
                    if (self::norm($b) === self::norm($o['bagian_in'])) $o['bagian'] = $b;
                }
            }
            $rows[] = $o;
        }

        return ['nama' => $nama, 'rows' => $rows];
    }
}
