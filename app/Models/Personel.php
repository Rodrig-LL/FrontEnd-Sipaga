<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['jenis', 'nama', 'pangkat', 'nrp', 'nrp_asli', 'jabatan', 'ket', 'seksi', 'tgl_lahir', 'bank', 'rekening',
    'nama_rekening', 'status', 'tgl_keluar', 'alasan_keluar', 'tandai'])]
class Personel extends Model
{
    protected $table = 'personel';

    public const JENIS = ['TNI', 'PNS'];

    public const BANK = ['BRI', 'BSI', 'BTN'];

    /** Kolom yang ikut dicatat di riwayat (nrp sengaja tidak: nrp_asli sudah mewakilinya). */
    public const KOLOM_RIWAYAT = ['jenis', 'nama', 'pangkat', 'nrp_asli', 'jabatan', 'ket', 'seksi', 'tgl_lahir', 'bank',
        'rekening', 'nama_rekening', 'status', 'tgl_keluar', 'alasan_keluar', 'tandai'];

    /** Batas panjang kolom teks; nilai dari Excel dipotong sebelum disimpan supaya satu sel panjang tidak menggagalkan seluruh impor. */
    public const PANJANG = ['jenis' => 3, 'nama' => 100, 'pangkat' => 60, 'nrp' => 30, 'nrp_asli' => 30, 'jabatan' => 100, 'ket' => 60,
        'seksi' => 100, 'bank' => 3, 'rekening' => 30, 'nama_rekening' => 100, 'alasan_keluar' => 200, 'tandai' => 200];

    protected function casts(): array
    {
        return ['tgl_lahir' => 'date:Y-m-d', 'tgl_keluar' => 'date:Y-m-d'];
    }

    /** Baris dengan kunci yang sama seperti tabel personel di personel_db.py (dipakai jembatan Python). */
    public function keBaris(): array
    {
        $tgl = fn ($d) => $d ? $d->format('Y-m-d') : '';

        return ['id' => $this->id, 'jenis' => $this->jenis, 'nama' => $this->nama, 'pangkat' => $this->pangkat, 'nrp' => $this->nrp,
            'nrp_asli' => $this->nrp_asli, 'jabatan' => $this->jabatan, 'ket' => $this->ket, 'seksi' => $this->seksi,
            'tgl_lahir' => $tgl($this->tgl_lahir), 'bank' => $this->bank, 'rekening' => $this->rekening,
            'nama_rekening' => $this->nama_rekening, 'status' => $this->status, 'tgl_keluar' => $tgl($this->tgl_keluar),
            'alasan_keluar' => $this->alasan_keluar, 'tandai' => $this->tandai,
            'dibuat' => (string) $this->created_at, 'diubah' => (string) $this->updated_at];
    }

    public function pinjaman(): HasMany
    {
        return $this->hasMany(Pinjaman::class);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', 'aktif');
    }

    /** Nilai kolom sebagai teks, sama seperti yang dicatat di riwayat (tanggal kosong = ""). */
    public function nilaiTeks(): array
    {
        $o = [];
        foreach (self::KOLOM_RIWAYAT as $k) {
            $v = $this->getAttribute($k);
            $o[$k] = $v === null ? '' : ($v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v);
        }

        return $o;
    }
}
