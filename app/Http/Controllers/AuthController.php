<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

        // Pesan galatnya sama untuk pengguna tak dikenal, sandi salah, dan akun nonaktif.
        if (! Auth::attempt(['username' => $data['pengguna'], 'password' => $data['sandi'], 'aktif' => true])) {
            throw ValidationException::withMessages(['sandi' => 'Nama pengguna atau kata sandi salah.']);
        }
        $request->session()->regenerate();

        return redirect()->intended(route('dasbor'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    }
}
