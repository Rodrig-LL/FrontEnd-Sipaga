<?php

namespace App\Jobs;

use App\Models\LogAktivitas;
use App\Models\ProsesGaji;
use App\Services\JembatanPython;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class JalankanProsesGaji implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public ProsesGaji $proses)
    {
        $this->timeout = config('sipaga.python_timeout') + 60;
    }

    public function handle(JembatanPython $jembatan): void
    {
        $jembatan->jalankan($this->proses);

        $p = $this->proses->refresh();
        LogAktivitas::catat($p->status === 'selesai' ? 'gaji_selesai' : 'gaji_gagal', "Proses #{$p->id}".($p->bulan ? ' '.$p->bulan : ''), $p->user);
    }

    public function failed(?Throwable $e): void
    {
        $this->proses->update(['status' => 'gagal', 'selesai_at' => now(), 'galat' => 'Proses terhenti tak terduga: '.($e?->getMessage() ?? '')]);
        LogAktivitas::catat('gaji_gagal', "Proses #{$this->proses->id} terhenti tak terduga", $this->proses->user);
    }
}
