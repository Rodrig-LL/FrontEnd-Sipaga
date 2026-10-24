@extends('layouts.app')
@section('title', 'Slip '.$r['nama'])

@section('konten')
<div class="no-print flex flex-col gap-3.5">
    @include('partials.kembali', ['href' => route('slip.index'), 'label' => 'Kembali ke daftar slip'])
    <header class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $r['nama'] }} <span class="font-normal text-muted">· {{ $r['pangkat'] }} · Oktober 2026</span></h1>
        <div class="flex flex-wrap items-center gap-2.5">
            @if ($r['n'] > 1)
                <a class="btn" aria-label="Personel sebelumnya" href="{{ route('slip.show', $r['n'] - 1) }}">←</a>
            @else
                <span class="btn off" aria-disabled="true">←</span>
            @endif
            <span class="muted font-mono">{{ $r['n'] }} / {{ $total }}</span>
            @if ($r['n'] < $total)
                <a class="btn" aria-label="Personel berikutnya" href="{{ route('slip.show', $r['n'] + 1) }}">→</a>
            @else
                <span class="btn off" aria-disabled="true">→</span>
            @endif
            <a class="btn" href="{{ route('slip.cetak', ['n' => $r['n']]) }}">Unduh PDF</a>
            <button type="button" class="btn btn-p" x-data x-on:click="window.print()">Cetak slip</button>
        </div>
    </header>
</div>

<div class="flex flex-wrap items-start gap-4 print:block">
    <div class="min-w-0 flex-[999_1_560px]">@include('partials.slip')</div>

    <aside class="no-print flex min-w-0 flex-[1_1_260px] flex-col gap-3">
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Status slip</h2>
            <p class="font-semibold {{ $r['stat'] === 'verif' ? 'text-alert' : '' }}">{{ ['siap' => 'Siap terbit', 'verif' => 'Perlu dicek', 'cetak' => 'Sudah dicetak'][$r['stat']] }}</p>
            <p class="muted mt-1.5 leading-normal">Sumber: softfile {{ $r['jenis'] }}, baris {{ $r['n'] }}. Dicocokkan otomatis 8 Okt 2026, 09.31.</p>
        </section>
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Dibanding bulan lalu</h2>
            <p class="muted leading-normal">
                @if ($r['stat'] === 'verif')
                    Ada komponen yang berubah dari September 2026. <a href="{{ route('proses.show', 4) }}">Tinjau di Proses Gaji</a>.
                @else
                    Tidak ada komponen yang berubah dari September 2026.
                @endif
            </p>
        </section>
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Riwayat slip</h2>
            <ul>
                @foreach (['September 2026', 'Agustus 2026', 'Juli 2026'] as $bulan)
                    <li class="flex justify-between gap-2 border-b border-[#EEECE4] py-2 text-[13px]"><span>{{ $bulan }}</span><span class="font-mono">{{ rp($r['bersih']) }}</span></li>
                @endforeach
            </ul>
        </section>
    </aside>
</div>
@endsection
