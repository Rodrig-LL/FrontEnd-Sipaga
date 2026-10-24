<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Pengaturan disimpan di session untuk prototipe; pindahkan ke tabel `pengaturan` nanti. */
class PengaturanController extends Controller
{
    public function edit()
    {
        return view('pengaturan', ['s' => session('pengaturan', [])]);
    }

    public function update(Request $request)
    {
        $d = $request->validate([
            'instansi' => ['nullable', 'string', 'max:120'], 'alamat' => ['nullable', 'string', 'max:200'],
            'kota' => ['nullable', 'string', 'max:60'], 'juru' => ['nullable', 'string', 'max:100'],
            'juru_nrp' => ['nullable', 'string', 'max:100'], 'operator' => ['nullable', 'string', 'max:100'],
        ]);
        $d['lega'] = $request->boolean('lega');
        $request->session()->put('pengaturan', $d);

        return back()->with('ok', 'Pengaturan disimpan.');
    }

    public function reset(Request $request)
    {
        $request->session()->flush();

        return redirect()->route('masuk');
    }
}
