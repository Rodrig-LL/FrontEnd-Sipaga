<?php

namespace App\Http\Controllers;

use App\Support\DataGaji;
use App\Support\Halaman;
use App\Support\Laporan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RekapController extends Controller
{
    public function index()
    {
        return view('rekap.index', ['daftar' => Laporan::DAFTAR, 'rep' => Laporan::build('bulanan'), 'ada' => DataGaji::ada(), 'hasil' => DataGaji::hasil()]);
    }

    public function show(Request $request, string $id)
    {
        abort_unless(isset(Laporan::DAFTAR[$id]), 404);
        $rep = Laporan::build($id);
        if (! $rep) return redirect()->route('rekap.index');
        $hal = null;
        $galat = null;
        $rows = $rep['rows'];
        // Tampilan layar dibagi 25 baris; ?cetak=1 memuat semua baris untuk dicetak.
        if ($rep['paged'] && ! $request->boolean('cetak')) {
            [$hal, $galat] = Halaman::bagi($rep['rows'], 25, $request);
            $rows = $hal->items();
        }

        return view('rekap.show', compact('rep', 'rows', 'hal', 'galat'));
    }

    public function unduh(string $id)
    {
        abort_unless(isset(Laporan::DAFTAR[$id]), 404);
        $rep = Laporan::build($id);
        if (! $rep) return redirect()->route('rekap.index');
        $periode = Str::slug(DataGaji::bulan());

        return response()->streamDownload(function () use ($rep) {
            echo Laporan::csv($rep);
        }, "sipaga-{$id}-{$periode}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
