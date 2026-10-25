<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\Pengaturan;
use Illuminate\Http\Request;

/** Kop dan penanda tangan slip. Tersimpan di database, jadi tidak hilang saat pengguna keluar. */
class PengaturanController extends Controller
{
    public function edit()
    {
        return view('pengaturan', ['s' => Pengaturan::semua()]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'instansi' => ['nullable', 'string', 'max:120'], 'alamat' => ['nullable', 'string', 'max:200'],
            'kota' => ['nullable', 'string', 'max:60'], 'juru' => ['nullable', 'string', 'max:100'],
            'juru_nrp' => ['nullable', 'string', 'max:100'],
        ]);
        $d['lega'] = $request->boolean('lega') ? '1' : '';
        Pengaturan::simpan(array_map(fn ($v) => (string) $v, $d));
        LogAktivitas::catat('pengaturan', 'Kop dan penanda tangan slip');

        return back()->with('ok', 'Pengaturan disimpan.');
    }
}
