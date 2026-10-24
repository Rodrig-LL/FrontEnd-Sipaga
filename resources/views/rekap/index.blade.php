@extends('layouts.app')
@section('title', 'Rekap & Laporan')

@section('konten')
@include('partials.kembali', ['href' => route('dasbor'), 'label' => 'Kembali ke Dasbor'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Laporan mengikuti periode yang dipilih</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Rekap &amp; Laporan</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        @include('partials.periode')
    </div>
</header>

@if (! $ada)
    @include('partials.belum-ada', ['judul' => 'Rekap dan laporan'])
@else
<section class="card">
    <h2>Unduh laporan</h2>
    <p class="muted mb-2 mt-1">Klik <b>Lihat</b> untuk membuka laporan, atau langsung unduh dalam format yang dibutuhkan.</p>
    <ul>
        @foreach ($daftar as $id => [$judul, $ket])
            <li class="flex flex-wrap items-center justify-between gap-3 border-b border-[#DAD8D0] py-3.5">
                <div class="min-w-0 flex-[1_1_360px]">
                    <div class="text-base font-semibold">{{ $judul }}</div>
                    <div class="muted mt-0.5">{{ $ket }}</div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a class="btn" href="{{ route('rekap.show', $id) }}">Lihat</a>
                    <a class="btn" href="{{ route('rekap.unduh', $id) }}">Unduh Excel (CSV)</a>
                    <a class="btn btn-o" href="{{ route('rekap.show', ['id' => $id, 'pdf' => 1]) }}">Unduh PDF</a>
                </div>
            </li>
        @endforeach
    </ul>
</section>

<section class="card">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-3">
        <h2>Rekap gaji {{ \App\Support\DataGaji::bulan() }}</h2>
        @if ($hasil['tingkat']['TINGGI'] > 0)
            <span class="inline-flex items-center gap-1.5 text-body"><span class="inline-flex text-alert">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span>{{ $hasil['tingkat']['TINGGI'] }} temuan tinggi belum diselesaikan</span>
        @endif
    </div>
    <div class="overflow-x-auto">@include('partials.tabel-laporan', ['rows' => $rep['rows']])</div>
</section>
@endif
@endsection
