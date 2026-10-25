<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Akun yang dinonaktifkan Admin langsung dikeluarkan, termasuk yang sudah terlanjur masuk. */
class AkunAktif
{
    public function handle(Request $request, Closure $next)
    {
        // === false: pengguna yang baru dibuat di memori belum punya atribut aktif (null), itu bukan nonaktif.
        if ($request->user() && $request->user()->aktif === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('masuk')->withErrors(['sandi' => 'Akun ini sudah dinonaktifkan. Hubungi Admin.']);
        }

        return $next($request);
    }
}
