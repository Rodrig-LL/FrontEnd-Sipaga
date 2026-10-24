<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pengaman sementara untuk prototipe: cukup ada "pengguna" di session.
 * Ganti dengan middleware 'auth' bawaan Laravel setelah login sungguhan dibuat.
 */
class DemoAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('demo_user')) {
            return redirect()->route('masuk');
        }

        return $next($request);
    }
}
