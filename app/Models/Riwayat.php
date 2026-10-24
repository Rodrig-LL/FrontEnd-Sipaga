<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Catatan perubahan; hanya bisa ditambah (dijaga trigger database). */
class Riwayat extends Model
{
    protected $table = 'riwayat';

    public const CREATED_AT = 'waktu';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['waktu' => 'datetime', 'perubahan' => 'array'];
    }
}
