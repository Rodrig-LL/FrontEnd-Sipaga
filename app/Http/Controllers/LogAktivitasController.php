<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use Illuminate\Http\Request;

/** Log aktivitas aplikasi (khusus Admin); hanya dibaca. */
class LogAktivitasController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $aksi = array_key_exists((string) $request->query('aksi'), LogAktivitas::AKSI) ? $request->query('aksi') : '';

        $hal = LogAktivitas::query()
            ->when($aksi !== '', fn ($w) => $w->where('aksi', $aksi))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('pengguna', 'like', "%$q%")->orWhere('username', 'like', "%$q%")
                ->orWhere('keterangan', 'like', "%$q%")->orWhere('ip', 'like', "%$q%")))
            ->latest('id')->paginate(25, ['*'], 'halaman')->withQueryString();

        return view('log.index', compact('hal', 'q', 'aksi') + ['total' => LogAktivitas::count()]);
    }
}
