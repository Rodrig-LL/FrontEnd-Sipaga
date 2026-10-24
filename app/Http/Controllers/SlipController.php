<?php

namespace App\Http\Controllers;

use App\Support\DataGaji;
use App\Support\Halaman;
use Illuminate\Http\Request;

class SlipController extends Controller
{
    public function index(Request $request)
    {
        if (! DataGaji::ada()) return view('slip.index', ['ada' => false]);

        $q = (string) $request->query('q', '');
        $status = in_array($request->query('status'), ['siap', 'verif'], true) ? $request->query('status') : '';
        $semua = array_values(DataGaji::slip());
        [$hal, $galat] = Halaman::bagi(DataGaji::saring($semua, $q, $status), 25, $request);

        return view('slip.index', compact('hal', 'galat', 'q', 'status') + [
            'ada' => true, 'total' => count($semua), 'verif' => count(array_filter($semua, fn ($r) => $r['stat'] === 'verif')),
        ]);
    }

    public function show(int $n)
    {
        $r = DataGaji::find($n);
        abort_unless($r, 404);
        $urut = array_keys(DataGaji::slip());
        $i = array_search($n, $urut, true);

        return view('slip.show', ['r' => $r, 'total' => count($urut), 'posisi' => $i + 1, 'sebelum' => $urut[$i - 1] ?? null, 'sesudah' => $urut[$i + 1] ?? null]);
    }

    /**
     * Halaman cetak / simpan PDF.
     *   /slip/cetak?ids[]=1&ids[]=2   slip yang dicentang
     *   /slip/cetak?semua=1           semua slip, dibagi 50 per kelompok agar laptop tidak berat
     *   /slip/cetak?n=5               satu slip
     */
    public function cetak(Request $request)
    {
        $all = DataGaji::slip();
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

        return view('slip.cetak', ['ada' => DataGaji::ada()] + compact('list', 'kembali', 'hal', 'galat'));
    }
}
