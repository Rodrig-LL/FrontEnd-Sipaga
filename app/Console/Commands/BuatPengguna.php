<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;

#[Signature('sipaga:pengguna {username : Nama pengguna untuk masuk} {nama : Nama lengkap} {--peran=operator : admin | juru_bayar | operator}')]
#[Description('Buat pengguna baru (kata sandi ditanyakan, tidak ikut riwayat perintah)')]
class BuatPengguna extends Command
{
    public function handle(): int
    {
        $peran = $this->option('peran');
        if (! array_key_exists($peran, User::PERAN)) {
            $this->error('Peran harus salah satu dari: '.implode(', ', array_keys(User::PERAN)));

            return self::FAILURE;
        }
        if (User::where('username', $this->argument('username'))->exists()) {
            $this->error('Nama pengguna itu sudah dipakai.');

            return self::FAILURE;
        }

        $sandi = password('Kata sandi (minimal 8 karakter)', required: true, validate: fn ($v) => strlen($v) < 8 ? 'Minimal 8 karakter.' : null);
        User::create(['username' => $this->argument('username'), 'name' => $this->argument('nama'), 'password' => $sandi, 'role' => $peran]);
        $this->info('Pengguna dibuat.');

        return self::SUCCESS;
    }
}
