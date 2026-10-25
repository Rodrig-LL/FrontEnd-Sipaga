<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Log aktivitas aplikasi; hanya bisa ditambah (dijaga trigger database). */
class LogAktivitas extends Model
{
    protected $table = 'log_aktivitas';

    public const CREATED_AT = 'waktu';

    public const UPDATED_AT = null;

    protected $guarded = [];

    public const AKSI = [
        'masuk' => 'Masuk', 'masuk_gagal' => 'Gagal masuk', 'keluar' => 'Keluar',
        'akun_tambah' => 'Akun ditambah', 'akun_ubah' => 'Akun diubah',
        'gaji_jalan' => 'Proses gaji dijalankan', 'gaji_selesai' => 'Proses gaji selesai', 'gaji_gagal' => 'Proses gaji gagal', 'gaji_hapus' => 'Proses gaji dihapus',
        'impor' => 'Impor Excel diterapkan',
        'personel_tambah' => 'Personel ditambah', 'personel_ubah' => 'Personel diubah', 'personel_hapus' => 'Personel dihapus',
        'potongan' => 'Potongan diubah', 'pengaturan' => 'Pengaturan diubah',
    ];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    /**
     * Catat satu kejadian. Pelaku diambil dari yang sedang masuk kecuali $oleh diberikan;
     * $username dipakai untuk percobaan masuk yang gagal (belum ada pelaku). Kata sandi tidak pernah dicatat.
     */
    public static function catat(string $aksi, string $keterangan = '', ?User $oleh = null, string $username = ''): void
    {
        $oleh ??= auth()->user();
        static::create([
            'user_id' => $oleh?->id, 'pengguna' => $oleh?->name ?? '', 'username' => $oleh?->username ?? mb_substr($username, 0, 60),
            'aksi' => $aksi, 'keterangan' => mb_substr($keterangan, 0, 500), 'ip' => (string) request()->ip(),
        ]);
    }
}
