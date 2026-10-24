<?php

use App\Support\Fmt;

/*
 * Fungsi bantu untuk view. Dimuat dari routes/web.php (require_once).
 * Nanti, bila sudah rapi, pindahkan ke "autoload.files" di composer.json.
 */

if (! function_exists('rp')) {
    /** Angka rupiah dengan titik ribuan: 1234567 -> 1.234.567 */
    function rp($n): string
    {
        return Fmt::rp($n);
    }
}

if (! function_exists('juta')) {
    function juta($n): string
    {
        return Fmt::juta($n);
    }
}

if (! function_exists('terbilang')) {
    function terbilang($n): string
    {
        return Fmt::terbilang($n);
    }
}
