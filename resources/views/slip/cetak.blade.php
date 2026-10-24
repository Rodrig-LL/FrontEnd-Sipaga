@extends('layouts.app')
@section('title', 'Cetak slip')

@section('konten')
@include('partials.kembali', ['href' => $kembali, 'label' => 'Kembali'])

@if (count($list) === 0)
    <section class="card no-print">
        <h2>Belum ada slip yang dipilih</h2>
        <p class="muted mt-1.5">Kembali ke daftar slip, beri tanda centang pada slip yang ingin dicetak, lalu klik Cetak.</p>
    </section>
@else
    <div class="no-print flex flex-col gap-4">
        <header>
            <p class="text-[13px] text-muted">Periode gaji · {{ session('periode', 'Oktober 2026') }}</p>
            <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Cetak / simpan PDF</h1>
        </header>
        <section class="card max-w-[720px] !px-6 !py-[22px]">
            <h2 class="!text-lg">{{ count($list) }} slip siap dicetak</h2>
            <ol class="my-3 list-decimal space-y-1 pl-[22px] text-base leading-relaxed" style="list-style: decimal">
                <li>Klik tombol <b>Cetak</b> di bawah.</li>
                <li>Di jendela cetak, pilih printer untuk mencetak di kertas, atau pilih <b>Simpan sebagai PDF</b>.</li>
                <li>Satu slip memakai satu halaman.</li>
            </ol>
            <button type="button" class="btn btn-p btn-lg" x-data x-on:click="window.print()">Cetak {{ count($list) }} slip</button>
            @if ($hal)
                <p class="muted mt-3">Semua slip dicetak per kelompok 50 agar laptop tidak berat. Pilih kelompok lain lewat kotak halaman di bawah.</p>
            @endif
        </section>
        @if ($hal)
            <section class="card !p-0">@include('partials.pager', ['label' => 'slip'])</section>
        @endif
    </div>

    <div>
        @foreach ($list as $i => $r)
            {{-- Di layar hanya slip pertama sebagai pratinjau; saat dicetak semuanya keluar. --}}
            <div class="{{ $i > 0 ? 'hidden print:block' : '' }}">@include('partials.slip', ['pb' => true])</div>
        @endforeach
    </div>
@endif
@endsection
