<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Admin hanya mengurus akun dan log; halaman kerja (gaji, personel, dsb.) dialihkan ke kelola akun. */
class BukanAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->punyaPeran('admin')) {
            return redirect()->route('akun.index');
        }

        return $next($request);
    }
}
