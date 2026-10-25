<?php

namespace App\Http\Controllers;

use App\Support\DataGaji;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        if (! DataGaji::ada()) return view('dashboard', ['ada' => false]);

        $slip = DataGaji::slip();
        $tinggi = array_values(array_filter(DataGaji::temuan(), fn ($t) => $t['tingkat'] === 'TINGGI'));

        return view('dashboard', [
            'ada' => true, 'agg' => DataGaji::agg(), 'hasil' => DataGaji::hasil(), 'p' => DataGaji::proses(),
            'total' => count($slip), 'siap' => count(array_filter($slip, fn ($r) => $r['stat'] === 'siap')),
            'tinggi' => count($tinggi), 'perhatian' => array_slice($tinggi, 0, 5),
        ]);
    }

    /** Ganti periode: pilih salah satu proses gaji yang sudah selesai (disimpan di session). */
    public function periode(Request $request)
    {
        DataGaji::pilih((int) $request->input('proses'));

        return back();
    }
}
