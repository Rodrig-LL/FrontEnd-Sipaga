<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'jenis', 'status', 'berkas', 'rencana', 'sidik', 'hasil', 'diterapkan_at'])]
class ImporPersonel extends Model
{
    protected $table = 'impor_personel';

    public const JENIS = ['nominatif' => 'Nominatif', 'bank' => 'Bank dan rekening', 'potongan' => 'Potongan dari pembukuan', 'arisan' => 'Arisan Persit'];

    protected function casts(): array
    {
        return ['berkas' => 'array', 'rencana' => 'array', 'hasil' => 'array', 'diterapkan_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Jumlah baris yang akan berubah bila diterapkan. */
    public function jumlahPerubahan(): int
    {
        $r = $this->rencana;

        return count($r['tambah'] ?? []) + count($r['ubah'] ?? []) + count($r['hilang'] ?? []) + count($r['pinjaman_baru'] ?? []) + count($r['berhenti'] ?? []);
    }
}
