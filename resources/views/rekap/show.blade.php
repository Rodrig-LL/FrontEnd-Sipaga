@extends('layouts.app')
@section('title', $rep['title'])

@section('konten')
@include('partials.kembali', ['href' => route('rekap.index'), 'label' => 'Kembali ke Rekap & Laporan'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Laporan · {{ \App\Support\DataGaji::bulan() }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $rep['title'] }}</h1>
        <p class="mt-1.5 text-[15px] text-body">{{ $rep['desc'] }}</p>
    </div>
    <div class="no-print flex flex-wrap items-end gap-2.5">
        <a class="btn" href="{{ route('rekap.unduh', $rep['id']) }}">Unduh Excel</a>
        @if ($rep['paged'])
            {{-- Cetak memuat semua baris di satu halaman lalu membuka jendela cetak. --}}
            <a class="btn btn-p" href="{{ route('rekap.show', ['id' => $rep['id'], 'cetak' => 1]) }}">Cetak / simpan PDF</a>
        @else
            <button type="button" class="btn btn-p" x-data x-on:click="window.print()">Cetak / simpan PDF</button>
        @endif
    </div>
</header>

@if (request()->boolean('pdf'))
    <p class="note no-print">Untuk menyimpan PDF: klik <b>Cetak / simpan PDF</b>, lalu di jendela cetak pilih <b>Simpan sebagai PDF</b> sebagai printer.</p>
@endif
@if (request()->boolean('cetak'))
    <p class="note no-print" x-data x-init="setTimeout(() => window.print(), 400)">Semua baris dimuat untuk dicetak. Jendela cetak akan terbuka sebentar lagi.
        <a href="{{ route('rekap.show', $rep['id']) }}">Kembali ke tampilan biasa</a>.</p>
@endif

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">@include('partials.tabel-laporan')</div>
    @if ($hal)
        @include('partials.pager', ['label' => 'baris'])
    @endif
</section>
@endsection
