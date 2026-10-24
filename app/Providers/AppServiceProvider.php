<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Hapus permanen hanya untuk data salah input; yang pindah/pensiun cukup ditandai keluar.
        Gate::define('kelola-potongan', fn (User $u) => $u->punyaPeran('admin', 'juru_bayar'));
        Gate::define('impor-personel', fn (User $u) => $u->punyaPeran('admin', 'juru_bayar'));
        Gate::define('ubah-pengaturan', fn (User $u) => $u->punyaPeran('admin', 'juru_bayar'));
        Gate::define('jalankan-proses', fn (User $u) => $u->punyaPeran('admin', 'juru_bayar'));
        Gate::define('hapus-personel', fn (User $u) => $u->punyaPeran('admin', 'juru_bayar'));
    }
}
