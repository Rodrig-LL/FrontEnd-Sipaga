@extends('layouts.app')
@section('title', 'Cetak slip')

@section('konten')
@include('partials.kembali', ['href' => $kembali, 'label' => 'Kembali'])

@if (! $ada)
    @include('partials.belum-ada', ['judul' => 'Cetak slip'])
@elseif (count($list) === 0)
    <section class="card no-print">
        <h2>Belum ada slip yang dipilih</h2>
        <p class="muted mt-1.5">Kembali ke daftar slip, beri tanda centang pada slip yang ingin dicetak, lalu klik Cetak.</p>
    </section>
@else
    <div class="no-print flex flex-col gap-4">
        <header>
            <p class="text-[13px] text-muted">Periode gaji · {{ \App\Support\DataGaji::bulan() }}</p>
            <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Cetak / simpan PDF</h1>
        </header>
        <section class="card max-w-[720px] !px-6 !py-[22px]">
            <h2 class="!text-lg">{{ count($list) }} slip siap dicetak</h2>
            @php $cek = count(array_filter($list, fn ($r) => $r['stat'] === 'verif')); @endphp
            @if ($cek)<p class="mt-2 flex gap-2 text-[15px]"><span class="mt-0.5 flex-none text-alert">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span><span><strong>{{ $cek }} slip berstatus perlu dicek.</strong> Selesaikan temuannya dulu sebelum slip itu dibagikan.</span></p>@endif
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
