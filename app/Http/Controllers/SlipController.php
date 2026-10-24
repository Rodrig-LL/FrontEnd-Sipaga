<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use App\Support\Halaman;
use Illuminate\Http\Request;

class SlipController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $jenis = (string) $request->query('jenis', '');
        $status = (string) $request->query('status', '');
        $list = Demo::saring(array_values(Demo::all()), $q, $jenis, $status, 'slip');
        [$hal, $galat] = Halaman::bagi($list, 25, $request);

        return view('slip.index', compact('hal', 'galat', 'q', 'jenis', 'status') + [
            'jumlah' => ['' => count(Demo::all()), 'TNI' => Demo::agg()['A']['TNI']['n'], 'PNS' => Demo::agg()['A']['PNS']['n']],
        ]);
    }

    public function show(int $n)
    {
        $r = Demo::find($n);
        abort_unless($r, 404);

        return view('slip.show', ['r' => $r, 'total' => count(Demo::all())]);
    }

    /**
     * Halaman cetak / simpan PDF.
     *   /slip/cetak?ids[]=1&ids[]=2   slip yang dicentang
     *   /slip/cetak?semua=1           semua slip, dibagi 50 per kelompok agar laptop tidak berat
     *   /slip/cetak?n=5               satu slip
     */
    public function cetak(Request $request)
    {
        $all = Demo::all();
        $kembali = route('slip.index');
        $hal = null;
        $galat = null;
        if ($request->filled('n')) {
            $r = $all[(int) $request->query('n')] ?? null;
            $list = $r ? [$r] : [];
            $kembali = $r ? route('slip.show', $r['n']) : $kembali;
        } elseif ($request->boolean('semua')) {
            [$hal, $galat] = Halaman::bagi(array_values($all), 50, $request);
            $list = $hal->items();
        } else {
            $ids = array_map('intval', (array) $request->query('ids', []));
            $list = array_values(array_filter(array_map(fn ($i) => $all[$i] ?? null, $ids)));
        }

        return view('slip.cetak', compact('list', 'kembali', 'hal', 'galat'));
    }
}
