<?php

namespace App\Support;

class Fmt
{
    public static function rp($n): string
    {
        $n = (int) round($n);

        return ($n < 0 ? '−' : '').number_format(abs($n), 0, ',', '.');
    }

    public static function juta($n): string
    {
        return $n >= 1e9
            ? number_format($n / 1e9, 2, ',', '.').' M'
            : number_format($n / 1e6, 1, ',', '.').' jt';
    }

    public static function terbilang($n): string
    {
        $s = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        $n = (int) floor($n);
        if ($n < 12) return $s[$n];
        if ($n < 20) return self::terbilang($n - 10).' belas';
        if ($n < 100) return self::terbilang(intdiv($n, 10)).' puluh'.($n % 10 ? ' '.self::terbilang($n % 10) : '');
        if ($n < 200) return 'seratus'.($n - 100 ? ' '.self::terbilang($n - 100) : '');
        if ($n < 1000) return self::terbilang(intdiv($n, 100)).' ratus'.($n % 100 ? ' '.self::terbilang($n % 100) : '');
        if ($n < 2000) return 'seribu'.($n - 1000 ? ' '.self::terbilang($n - 1000) : '');
        if ($n < 1000000) return self::terbilang(intdiv($n, 1000)).' ribu'.($n % 1000 ? ' '.self::terbilang($n % 1000) : '');
        if ($n < 1000000000) return self::terbilang(intdiv($n, 1000000)).' juta'.($n % 1000000 ? ' '.self::terbilang($n % 1000000) : '');

        return '';
    }
}
