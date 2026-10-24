<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['personel_id', 'pemberi', 'cicilan', 'mulai', 'tenor', 'tgl_cair', 'catatan', 'status'])]
class Pinjaman extends Model
{
    protected $table = 'pinjaman';

    public function personel(): BelongsTo
    {
        return $this->belongsTo(Personel::class);
    }
}
