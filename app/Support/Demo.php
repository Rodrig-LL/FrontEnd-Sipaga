<?php

namespace App\Support;

/**
 * DATA CONTOH untuk prototipe frontend (512 personel, deterministik).
 *
 * Semua controller membaca data lewat kelas ini, jadi saat backend siap
 * cukup ganti isi method-method di sini (atau buat repository Eloquent)
 * tanpa menyentuh view. Perubahan yang dilakukan operator saat uji coba
 * (tambah/ubah personel) disimpan di session, bukan database.
 */
class Demo
{
    // [pangkat, gaji pokok, tunjangan jabatan/fungsional]
    public const TNI = [
        ['Letkol Inf', 5620000, 780000], ['Mayor Inf', 4910000, 540000], ['Kapten Caj', 4520000, 480000],
        ['Lettu Caj', 4418000, 420000], ['Pelda', 4092300, 330000], ['Peltu', 3984000, 300000],
        ['Serma', 3981600, 270000], ['Serka', 3420000, 180000], ['Sertu', 3210000, 160000],
        ['Kopka', 3050000, 140000], ['Kopda', 2860000, 120000], ['Praka', 2710000, 100000], ['Prada', 2480000, 90000],
    ];

    public const PNS = [
        ['Penata Tk. I · III/d', 4120900, 720000], ['Penata · III/c', 3640000, 610000],
        ['Penata Muda Tk. I · III/b', 3183400, 520000], ['Penata Muda · III/a', 2898100, 450000],
        ['Pengatur Tk. I · II/d', 2744300, 340000], ['Pengatur · II/c', 2663800, 300000],
        ['Pengatur Muda Tk. I · II/b', 2560000, 270000], ['Pengatur Muda · II/a', 2184000, 240000],
    ];

    public const FIRST = ['Ahmad', 'Budi', 'Cahyo', 'Dedi', 'Eko', 'Fajar', 'Gunawan', 'Hadi', 'Irfan', 'Joko', 'Lukman', 'Mulyadi', 'Nanang', 'Oky', 'Rahmat', 'Slamet', 'Taufik', 'Umar', 'Wahyu', 'Yudi', 'Agus', 'Bambang', 'Dwi', 'Heri', 'Imam', 'Rizal', 'Syahrul', 'Tri', 'Siti', 'Dewi', 'Rina', 'Wulan', 'Nurul', 'Maya', 'Ani', 'Sri', 'Fitri', 'Putri', 'Ratna', 'Dian', 'Indah', 'Endang', 'Retno', 'Tuti'];

    public const LAST = ['Santoso', 'Wibowo', 'Hidayat', 'Saputra', 'Nugroho', 'Setiawan', 'Prasetyo', 'Kurniawan', 'Rahman', 'Susilo', 'Hartono', 'Purnomo', 'Utomo', 'Firmansyah', 'Pratama', 'Wijaya', 'Gunawan', 'Hakim', 'Permana', 'Lestari', 'Handayani', 'Anggraini', 'Rahayu', 'Wulandari', 'Puspita', 'Kusuma', 'Maulana', 'Siregar', 'Nasution', 'Sihombing'];

    public const BAGIAN = ['Bag. Personel', 'Bag. Keuangan', 'Bag. Umum', 'Bag. Logistik', 'Bag. Hukum'];

    public const PERIODE = ['Oktober 2026', 'September 2026', 'Agustus 2026'];

    private static ?array $dasar = null;

    /* ------------------------------------------------------------ */

    /** Daftar pangkat/golongan menurut jenis. */
    public static function pangkat(string $jenis): array
    {
        return array_column($jenis === 'TNI' ? self::TNI : self::PNS, 0);
    }

    private static function rank(string $jenis, string $nama): array
    {
        $list = $jenis === 'TNI' ? self::TNI : self::PNS;
        foreach ($list as $r) {
            if ($r[0] === $nama) return $r;
        }

        return $list[count($list) - 1];
    }

    private static function rnd(): float
    {
        return mt_rand() / mt_getrandmax();
    }

    private static function r10($n): int
    {
        return (int) (round($n / 10) * 10);
    }

    /** Hitung satu slip dari data dasar personel. */
    public static function build(array $o, int $n): array
    {
        $rk = self::rank($o['jenis'], $o['pangkat']);
        $anak = $o['anak'] ?? 0;
        $kawin = $o['kawin'] ?? ($anak > 0);
        $tni = $o['jenis'] === 'TNI';
        $pph = $o['pph'] ?? self::r10(20000 + self::rnd() * 70000);
        $sewa = array_key_exists('sewa', $o) ? $o['sewa'] : ($tni && self::rnd() < 0.35 ? 115620 : 0);
        $kop = array_key_exists('kop', $o) ? $o['kop'] : [0, 150000, 350000, 500000][mt_rand(0, 3)];
        $p = $rk[1];
        $istri = $kawin ? self::r10($p * 0.10) : 0;
        $tAnak = $anak ? self::r10($p * 0.02 * min($anak, 2)) : 0;

        $earn = [['Gaji pokok', $p]];
        if ($kawin) $earn[] = ['Tunjangan istri / suami', $istri];
        if ($anak) $earn[] = ['Tunjangan anak', $tAnak];
        $earn[] = [$tni ? 'Tunjangan jabatan' : 'Tunjangan fungsional', $rk[2]];
        $earn[] = ['Tunjangan beras', 289680];
        $earn[] = [$tni ? 'Uang lauk pauk' : 'Tunjangan umum', $tni ? 1350000 : 185000];
        $earn[] = ['Tunjangan PPh 21', $pph];

        $ded = [
            ['Iuran pensiun & THT', self::r10($p * 0.0912)],
            ['Iuran BPJS Kesehatan', self::r10(($p + $istri + $tAnak) * 0.01)],
            ['PPh 21', $pph],
        ];
        if ($sewa) $ded[] = ['Sewa rumah dinas', $sewa];
        if ($kop) $ded[] = ['Koperasi', $kop];

        $kotor = array_sum(array_column($earn, 1));
        $pot = array_sum(array_column($ded, 1));
        $kurang = ! empty($o['kurang']);

        return [
            'n' => $n,
            'nama' => $o['nama'], 'jenis' => $o['jenis'], 'pangkat' => $o['pangkat'], 'nrp' => $o['nrp'],
            'bagian' => $kurang ? '—' : ($o['bagian'] ?? self::BAGIAN[mt_rand(0, 4)]),
            'kel' => $kurang ? '—' : ($kawin ? 'Kawin · '.$anak.' anak' : 'Belum kawin'),
            'kurang' => $kurang,
            'pensiun' => $o['pensiun'] ?? '',
            'stat' => $kurang ? 'verif' : ($o['stat'] ?? 'siap'),   // siap | verif | cetak
            'earn' => $earn, 'ded' => $ded,
            'kotor' => $kotor, 'pot' => $pot, 'bersih' => $kotor - $pot,
            'anak' => $anak, 'kawin' => $kawin,
            'src' => ['pph' => $pph, 'sewa' => $sewa, 'kop' => $kop],
        ];
    }

    private static function dasar(): array
    {
        if (self::$dasar !== null) return self::$dasar;
        mt_srand(20261013);
        $data = [];
        $add = function (array $o) use (&$data) {
            $data[] = self::build($o, count($data) + 1);
        };

        foreach ([
            ['nama' => 'Ahmad Fauzi', 'jenis' => 'TNI', 'pangkat' => 'Mayor Inf', 'nrp' => '11•••2214', 'bagian' => 'Bag. Personel', 'anak' => 2, 'pph' => 65220, 'sewa' => 115620, 'kop' => 350000],
            ['nama' => 'Teguh Santoso', 'jenis' => 'TNI', 'pangkat' => 'Lettu Caj', 'nrp' => '11•••5506', 'bagian' => 'Bag. Umum', 'anak' => 2, 'stat' => 'cetak'],
            ['nama' => 'Dewi Anggraini', 'jenis' => 'PNS', 'pangkat' => 'Penata Tk. I · III/d', 'nrp' => '1982•••3007', 'bagian' => 'Bag. Keuangan', 'anak' => 1],
            ['nama' => 'Rudi Setiawan', 'jenis' => 'TNI', 'pangkat' => 'Peltu', 'nrp' => '21•••3301', 'bagian' => 'Bag. Logistik', 'anak' => 3, 'stat' => 'cetak', 'pensiun' => 'Des 2026'],
            ['nama' => 'Dimas Prasetyo', 'jenis' => 'TNI', 'pangkat' => 'Serka', 'nrp' => '31•••4471', 'bagian' => 'Bag. Umum', 'anak' => 3, 'stat' => 'verif'],
            ['nama' => 'Wulan Sari', 'jenis' => 'PNS', 'pangkat' => 'Penata Muda Tk. I · III/b', 'nrp' => '1991•••1504', 'bagian' => 'Bag. Personel', 'anak' => 0, 'kawin' => false],
            ['nama' => 'Fajar Nugroho', 'jenis' => 'TNI', 'pangkat' => 'Kopda', 'nrp' => '31•••6672', 'bagian' => 'Bag. Logistik', 'anak' => 1],
            ['nama' => 'Nurul Hidayah', 'jenis' => 'PNS', 'pangkat' => 'Pengatur Tk. I · II/d', 'nrp' => '1988•••0611', 'bagian' => 'Bag. Keuangan', 'anak' => 2],
            ['nama' => 'Bayu Saputra', 'jenis' => 'TNI', 'pangkat' => 'Praka', 'nrp' => '31•••8820', 'bagian' => 'Bag. Logistik', 'anak' => 1, 'stat' => 'verif'],
            ['nama' => 'Rina Kartika', 'jenis' => 'PNS', 'pangkat' => 'Penata Muda · III/a', 'nrp' => '1990•••2003', 'bagian' => 'Bag. Personel', 'anak' => 0, 'kawin' => false, 'stat' => 'verif'],
            ['nama' => 'Yoga Pratama', 'jenis' => 'TNI', 'pangkat' => 'Sertu', 'nrp' => '21•••7718', 'kurang' => true],
            ['nama' => 'Andi Kurniawan', 'jenis' => 'TNI', 'pangkat' => 'Praka', 'nrp' => '31•••5590', 'kurang' => true],
        ] as $o) {
            $add($o);
        }

        $q = array_merge(array_fill(0, 310, 'TNI'), array_fill(0, 190, 'PNS'));
        for ($i = count($q) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$q[$i], $q[$j]] = [$q[$j], $q[$i]];
        }
        $kurangAt = [40, 95, 160, 230, 310];
        foreach ($q as $k => $jenis) {
            $L = $jenis === 'TNI' ? self::TNI : self::PNS;
            $idx = count($L) - 1 - (int) floor(self::rnd() * self::rnd() * count($L));
            $nrp = $jenis === 'TNI'
                ? ['11', '21', '31'][mt_rand(0, 2)].'•••'.(1000 + mt_rand(0, 8999))
                : '19'.(70 + mt_rand(0, 29)).'•••'.(1000 + mt_rand(0, 8999));
            $anak = mt_rand(0, 3);
            $kawin = $anak > 0 || self::rnd() < 0.2;
            $add([
                'nama' => self::FIRST[mt_rand(0, count(self::FIRST) - 1)].' '.self::LAST[mt_rand(0, count(self::LAST) - 1)],
                'jenis' => $jenis, 'pangkat' => $L[$idx][0], 'nrp' => $nrp,
                'anak' => $anak, 'kawin' => $kawin,
                'stat' => ($k % 19 === 7) ? 'verif' : (self::rnd() < 0.2 ? 'cetak' : 'siap'),
                'kurang' => in_array($k, $kurangAt, true),
                'pensiun' => ($k % 37 === 5) ? 'Mar 2027' : '',
            ]);
        }

        return self::$dasar = $data;
    }

    /* ------------------------------------------------------------ */

    /**
     * Seluruh personel (data dasar + perubahan operator di session).
     * Kunci array = nomor urut (1-based).
     */
    public static function all(): array
    {
        $data = self::dasar();
        foreach (session('demo.baru', []) as $o) {
            $data[] = self::build($o, count($data) + 1);
        }
        foreach (session('demo.ubah', []) as $n => $o) {
            if (isset($data[$n - 1])) $data[$n - 1] = self::build($o, (int) $n);
        }

        $out = [];
        foreach ($data as $r) $out[$r['n']] = $r;

        return $out;
    }

    public static function find(int $n): ?array
    {
        return self::all()[$n] ?? null;
    }

    /** Daftar personel; yang datanya belum lengkap ditaruh paling atas. */
    public static function personelUrut(): array
    {
        $all = array_values(self::all());
        usort($all, fn ($a, $b) => [$b['kurang'], $a['n']] <=> [$a['kurang'], $b['n']]);

        return $all;
    }

    /** Simpan personel baru / perubahan ke session. Mengembalikan nomor urutnya. */
    public static function simpan(?int $n, array $o): int
    {
        if ($n) {
            $r = self::find($n);
            $o += ['pph' => $r['src']['pph'], 'sewa' => $r['src']['sewa'], 'kop' => $r['src']['kop'],
                'stat' => $r['kurang'] ? 'siap' : $r['stat'], 'pensiun' => $r['pensiun'], 'kurang' => false];
            $ubah = session('demo.ubah', []);
            $ubah[$n] = $o;
            session(['demo.ubah' => $ubah]);

            return $n;
        }
        $o += ['pph' => 45000, 'sewa' => 0, 'kop' => 0, 'stat' => 'siap'];
        $baru = session('demo.baru', []);
        $baru[] = $o;
        session(['demo.baru' => $baru]);

        return count(self::dasar()) + count($baru);
    }

    /** Jumlah per jenis, per komponen (untuk dasbor dan rekap). */
    public static function agg(): array
    {
        $A = ['TNI' => ['n' => 0, 'earn' => [], 'ded' => [], 'kotor' => 0, 'pot' => 0, 'bersih' => 0],
              'PNS' => ['n' => 0, 'earn' => [], 'ded' => [], 'kotor' => 0, 'pot' => 0, 'bersih' => 0]];
        $eo = [];
        $do = [];
        foreach (self::all() as $r) {
            $a = &$A[$r['jenis']];
            $a['n']++;
            $a['kotor'] += $r['kotor'];
            $a['pot'] += $r['pot'];
            $a['bersih'] += $r['bersih'];
            foreach ($r['earn'] as [$l, $v]) {
                $a['earn'][$l] = ($a['earn'][$l] ?? 0) + $v;
                if (! in_array($l, $eo, true)) $eo[] = $l;
            }
            foreach ($r['ded'] as [$l, $v]) {
                $a['ded'][$l] = ($a['ded'][$l] ?? 0) + $v;
                if (! in_array($l, $do, true)) $do[] = $l;
            }
            unset($a);
        }

        return ['A' => $A, 'earn' => $eo, 'ded' => $do];
    }

    /**
     * Saring daftar menurut kata kunci, jenis, dan status.
     * $jenisDaftar: 'slip' (status = siap/verif/cetak) atau 'personel' (status = aktif/kurang/pensiun).
     */
    public static function saring(array $list, string $q, string $jenis, string $status, string $daftar): array
    {
        $q = mb_strtolower(trim($q));

        return array_values(array_filter($list, function ($r) use ($q, $jenis, $status, $daftar) {
            if ($jenis !== '' && $r['jenis'] !== $jenis) return false;
            if ($q !== '' && ! str_contains(mb_strtolower($r['nama']), $q) && ! str_contains(mb_strtolower($r['nrp']), $q)) return false;
            if ($status !== '') {
                $s = $daftar === 'slip' ? $r['stat'] : ($r['kurang'] ? 'kurang' : ($r['pensiun'] ? 'pensiun' : 'aktif'));
                if ($s !== $status) return false;
            }

            return true;
        }));
    }

    /* ---------- data alur "Proses Gaji" ---------- */

    /** 7 data yang NRP/NIP-nya tidak cocok. */
    public static function tidakCocok(): array
    {
        return [
            ['key' => 'm1', 'nama' => 'Hendra Wibowo', 'pangkat' => 'Kapten Caj', 'id' => '11•••0932', 'jenis' => 'TNI', 'masalah' => 'NRP tidak ada di softfile', 'aksi' => 'Tautkan manual'],
            ['key' => 'm2', 'nama' => 'Yoga Pratama', 'pangkat' => 'Sertu', 'id' => '21•••7718', 'jenis' => 'TNI', 'masalah' => 'Belum ada di data personel', 'aksi' => 'Tambah personel'],
            ['key' => 'm3', 'nama' => 'Lestari Ningsih', 'pangkat' => 'Pengatur Muda · II/a', 'id' => '1996•••2201', 'jenis' => 'PNS', 'masalah' => 'NIP beda 1 digit (…2201 / …2210)', 'aksi' => 'Tautkan manual'],
            ['key' => 'm4', 'nama' => 'Andi Kurniawan', 'pangkat' => 'Praka', 'id' => '31•••5590', 'jenis' => 'TNI', 'masalah' => 'Mutasi masuk, belum ada di data personel', 'aksi' => 'Tambah personel'],
            ['key' => 'm5', 'nama' => 'Sri Wahyuni', 'pangkat' => 'Pengatur Muda Tk. I · II/b', 'id' => '1993•••0812', 'jenis' => 'PNS', 'masalah' => 'NIP tidak ada di softfile', 'aksi' => 'Tautkan manual'],
            ['key' => 'm6', 'nama' => 'Bambang Hartono', 'pangkat' => 'Pelda', 'id' => '21•••4402', 'jenis' => 'TNI', 'masalah' => 'NRP tidak ada di softfile', 'aksi' => 'Tautkan manual'],
            ['key' => 'm7', 'nama' => 'Maya Puspita', 'pangkat' => 'Penata Muda · III/a', 'id' => '1994•••1107', 'jenis' => 'PNS', 'masalah' => 'Nama berbeda ejaan (Maya Puspitasari)', 'aksi' => 'Tautkan manual'],
        ];
    }

    /** 19 komponen yang angkanya berbeda dari bulan lalu. */
    public static function selisih(): array
    {
        $rows = [
            ['Dimas Prasetyo', 'Serka', '31•••4471', 'TNI', 'Tunjangan anak (2 → 3)', '196.500', '294.750', '+98.250'],
            ['Rina Kartika', 'Penata Muda · III/a', '1990•••2003', 'PNS', 'Gaji pokok', '2.785.700', '2.898.100', '+112.400'],
            ['Agus Salim', 'Pengatur · II/c', '1985•••1009', 'PNS', 'Potongan PPh 21', '41.200', 'tak terbaca', '—'],
            ['Bayu Saputra', 'Praka', '31•••8820', 'TNI', 'Tunjangan beras', '289.680', '217.260', '−72.420'],
            ['Siti Rahmawati', 'Penata · III/c', '1987•••4410', 'PNS', 'Tunjangan fungsional', '540.000', '0', '−540.000'],
            ['Eko Purnomo', 'Kopka', '31•••2287', 'TNI', 'Tunjangan jabatan', '0', '360.000', '+360.000'],
            ['Ratna Dewi', 'Penata · III/c', '1986•••0915', 'PNS', 'Iuran BPJS Kesehatan', '58.200', '61.400', '+3.200'],
            ['Joko Susilo', 'Serma', '21•••6604', 'TNI', 'Gaji pokok', '3.981.600', '4.092.300', '+110.700'],
            ['Hartini', 'Pengatur Tk. I · II/d', '1984•••0302', 'PNS', 'Tunjangan anak (1 → 2)', '65.300', '130.600', '+65.300'],
            ['Slamet Riyadi', 'Pelda', '21•••1145', 'TNI', 'Potongan koperasi', '350.000', '500.000', '+150.000'],
            ['Ani Suryani', 'Penata Muda Tk. I · III/b', '1992•••0730', 'PNS', 'Tunjangan istri / suami', '0', '289.000', '+289.000'],
            ['Wahyu Hidayat', 'Sertu', '21•••8031', 'TNI', 'Uang lauk pauk', '1.350.000', '1.305.000', '−45.000'],
            ['Dian Permata', 'Pengatur · II/c', '1990•••1218', 'PNS', 'Gaji pokok', '2.663.800', '2.744.300', '+80.500'],
            ['Rizal Fahmi', 'Kopda', '31•••3390', 'TNI', 'Sewa rumah dinas', '115.620', '0', '−115.620'],
            ['Sri Lestari', 'Penata · III/c', '1985•••0517', 'PNS', 'Potongan PPh 21', '62.400', '71.900', '+9.500'],
            ['Hendro Gunawan', 'Serka', '31•••5512', 'TNI', 'Tunjangan anak (3 → 2)', '294.750', '196.500', '−98.250'],
            ['Puji Astuti', 'Pengatur Muda Tk. I · II/b', '1995•••0904', 'PNS', 'Tunjangan umum', '175.000', '185.000', '+10.000'],
            ['Arif Rahman', 'Praka', '31•••7745', 'TNI', 'Iuran pensiun & THT', '213.450', '219.990', '+6.540'],
            ['Yuliana', 'Penata Muda · III/a', '1993•••1126', 'PNS', 'Tunjangan beras', '217.260', '289.680', '+72.420'],
        ];

        return array_map(fn ($a, $i) => [
            'key' => 's'.$i, 'nama' => $a[0], 'pangkat' => $a[1], 'id' => $a[2], 'jenis' => $a[3],
            'komponen' => $a[4], 'lama' => $a[5], 'baru' => $a[6], 'selisih' => $a[7],
        ], $rows, array_keys($rows));
    }
}
