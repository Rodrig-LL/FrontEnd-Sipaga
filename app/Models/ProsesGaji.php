<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['user_id', 'status', 'bulan', 'bulan_paksa', 'berkas', 'ringkasan', 'galat', 'mulai_at', 'selesai_at'])]
class ProsesGaji extends Model
{
    protected $table = 'proses_gaji';

    protected function casts(): array
    {
        return ['berkas' => 'array', 'ringkasan' => 'array', 'mulai_at' => 'datetime', 'selesai_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Folder kerja di disk lokal: masuk/ (unggahan), hasil/ (keluaran Python), tugas.json, hasil.json. */
    public function folder(string $sub = ''): string
    {
        return 'proses/'.$this->id.($sub !== '' ? '/'.$sub : '');
    }

    public function path(string $sub = ''): string
    {
        return Storage::disk('local')->path($this->folder($sub));
    }

    public function selesai(): bool
    {
        return in_array($this->status, ['selesai', 'gagal'], true);
    }

    /** Isi hasil.json (ringkasan + rincian per orang); null bila belum ada. */
    public function hasil(): ?array
    {
        $disk = Storage::disk('local');

        return $disk->exists($this->folder('hasil.json')) ? json_decode($disk->get($this->folder('hasil.json')), true) : null;
    }

    /** Berkas keluaran Python yang boleh diunduh, nama => ukuran. */
    public function berkasHasil(): array
    {
        $disk = Storage::disk('local');
        $o = [];
        foreach ($disk->files($this->folder('hasil')) as $f) $o[basename($f)] = $disk->size($f);

        return $o;
    }
}
