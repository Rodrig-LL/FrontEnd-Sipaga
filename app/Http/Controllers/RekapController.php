<?php

namespace App\Http\Controllers;

use App\Support\Halaman;
use App\Support\Laporan;
use Illuminate\Http\Request;

class RekapController extends Controller
{
    public function index()
    {
        return view('rekap.index', ['daftar' => Laporan::DAFTAR, 'rep' => Laporan::build('bulanan')]);
    }

    public function show(Request $request, string $id)
    {
        $rep = Laporan::build($id);
        abort_unless($rep, 404);
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
        $rep = Laporan::build($id);
        abort_unless($rep, 404);
        $periode = \Illuminate\Support\Str::slug(session('periode', 'Oktober 2026'));

        return response()->streamDownload(function () use ($rep) {
            echo Laporan::csv($rep);
        }, "sipaga-{$id}-{$periode}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
