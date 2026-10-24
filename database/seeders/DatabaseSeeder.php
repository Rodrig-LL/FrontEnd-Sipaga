<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Pengguna contoh hanya untuk pengembangan lokal. Di server, buat pengguna dengan
     * `php artisan sipaga:pengguna` supaya kata sandinya tidak tertulis di kode.
     */
    public function run(): void
    {
        if (! app()->environment('local')) return;

        foreach ([['admin', 'Admin SIPAGA', 'admin'], ['jurubayar', 'Juru Bayar', 'juru_bayar'], ['operator', 'Operator', 'operator']] as [$u, $nama, $peran]) {
            User::updateOrCreate(['username' => $u], ['name' => $nama, 'role' => $peran, 'aktif' => true, 'password' => 'password']);
        }
    }
}
