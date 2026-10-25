<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
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
            LogAktivitas::catat('masuk_gagal', 'Nama pengguna atau kata sandi salah, atau akun nonaktif.', null, $data['pengguna']);
            throw ValidationException::withMessages(['sandi' => 'Nama pengguna atau kata sandi salah.']);
        }
        $request->session()->regenerate();
        LogAktivitas::catat('masuk');

        return redirect()->intended(route(Auth::user()->punyaPeran('admin') ? 'akun.index' : 'dashboard'));
    }

    public function destroy(Request $request)
    {
        LogAktivitas::catat('keluar');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('masuk');
    }
}
