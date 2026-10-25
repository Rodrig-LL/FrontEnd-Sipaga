<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan kop dan penanda tangan slip, satu baris per kunci. Berlaku untuk semua pengguna. */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public const KUNCI = ['instansi', 'alamat', 'kota', 'juru', 'juru_nrp', 'lega'];

    private static ?array $cache = null;

    /** @return array<string, string> */
    public static function semua(): array
    {
        return self::$cache ??= self::query()->pluck('nilai', 'kunci')->all();
    }

    public static function ambil(string $kunci, string $bawaan = ''): string
    {
        $v = self::semua()[$kunci] ?? '';

        return $v !== '' ? $v : $bawaan;
    }

    public static function simpan(array $nilai): void
    {
        foreach (self::KUNCI as $k) {
            if (array_key_exists($k, $nilai)) self::updateOrCreate(['kunci' => $k], ['nilai' => (string) $nilai[$k]]);
        }
        self::$cache = null;
    }
}
