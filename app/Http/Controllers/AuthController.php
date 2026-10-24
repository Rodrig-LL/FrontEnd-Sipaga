<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/** Login PROTOTIPE: nama pengguna & kata sandi apa saja. Ganti dengan autentikasi sungguhan. */
class AuthController extends Controller
{
    public function create()
    {
        return view('masuk');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pengguna' => ['required', 'string', 'max:60'],
            'sandi' => ['required', 'string'],
        ], [
            'pengguna.required' => 'Isi nama pengguna.',
            'sandi.required' => 'Isi kata sandi.',
        ]);
        $request->session()->regenerate();
        $request->session()->put('demo_user', $data['pengguna']);

        return redirect()->route('dasbor');
    }

    public function destroy(Request $request)
    {
        $request->session()->forget('demo_user');

        return redirect()->route('masuk');
    }
}
