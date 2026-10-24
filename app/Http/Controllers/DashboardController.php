<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return view('dasbor', [
            'agg' => Demo::agg(),
            'total' => count(Demo::all()),
            'perhatian' => [
                ['Hendra Wibowo', 'Kapten Caj', '11•••0932', 'TNI', true, 'NRP tidak ada di softfile'],
                ['Yoga Pratama', 'Sertu', '21•••7718', 'TNI', true, 'Belum ada di data personel'],
                ['Dimas Prasetyo', 'Serka', '31•••4471', 'TNI', false, 'Tunjangan anak berubah (2 → 3)'],
                ['Rina Kartika', 'Penata Muda · III/a', '1990•••2003', 'PNS', false, 'Gaji pokok naik Rp 112.400'],
                ['Agus Salim', 'Pengatur · II/c', '1985•••1009', 'PNS', false, 'Potongan PPh 21 tidak terbaca'],
            ],
        ]);
    }

    /** Ganti periode (disimpan di session; data contoh tetap Oktober 2026). */
    public function periode(Request $request)
    {
        $p = $request->input('periode');
        if (in_array($p, array_merge(Demo::PERIODE, ['Januari–Desember 2026']), true)) {
            $request->session()->put('periode', $p);
        }

        return back();
    }
}
