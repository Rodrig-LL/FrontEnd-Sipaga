<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class Halaman
{
    /**
     * Potong daftar menjadi halaman. Kalau operator mengetik nomor halaman
     * yang tidak ada, tampilkan halaman terdekat dan kembalikan pesan galat.
     *
     * @return array{0: LengthAwarePaginator, 1: ?string}  [paginator, pesan galat]
     */
    public static function bagi(array $items, int $per, Request $request): array
    {
        $total = count($items);
        $last = max(1, (int) ceil($total / $per));
        $raw = $request->query('halaman', '1');
        $page = (int) $raw;
        $galat = null;
        if (! ctype_digit((string) $raw) || $page < 1 || $page > $last) {
            $galat = 'Nomor halaman harus antara 1 dan '.$last.'.';
            $page = min(max($page, 1), $last);
        }
        $slice = array_slice($items, ($page - 1) * $per, $per);
        $p = new LengthAwarePaginator($slice, $total, $per, $page, [
            'path' => $request->url(),
            'query' => $request->except('halaman'),
            'pageName' => 'halaman',
        ]);

        return [$p, $galat];
    }
}
