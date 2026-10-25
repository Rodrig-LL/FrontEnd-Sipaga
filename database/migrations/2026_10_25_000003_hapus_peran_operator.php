<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran Operator dihapus: aplikasi hanya punya Admin dan Juru Bayar.
 * Akun Operator yang sudah ada dijadikan Juru Bayar tetapi dinonaktifkan, supaya tidak ada yang
 * diam-diam mendapat hak lebih besar; Admin yang memutuskan di Akun Pengguna (aktifkan atau biarkan).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'operator')->update(['role' => 'juru_bayar', 'aktif' => false]);
    }

    public function down(): void
    {
        // Tidak bisa dikembalikan: akun yang dulu Operator tidak lagi dibedakan.
    }
};
