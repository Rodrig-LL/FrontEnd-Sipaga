@extends('layouts.app')
@section('title', 'Dasbor')

@php
    $T = $agg['A']['TNI'];
    $N = $agg['A']['PNS'];
    $pt = (int) round($T['n'] / $total * 100);
@endphp

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Periode gaji · {{ session('periode', 'Oktober 2026') }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Dasbor</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        <span class="badge">Data contoh</span>
        @include('partials.periode')
        <a class="btn btn-p" href="{{ route('proses') }}">Lanjutkan proses gaji</a>
    </div>
</header>
@include('partials.catatan-periode')

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan">
    <div class="kpi"><div class="l">Total personel</div><div class="v">{{ $total }}</div><div class="s">{{ $T['n'] }} TNI · {{ $N['n'] }} PNS</div></div>
    <div class="kpi"><div class="l">Slip siap terbit</div><div class="v">486</div><div class="s">94,9% dari 512 personel</div></div>
    <div class="kpi"><div class="l">Perlu verifikasi</div><div class="v">19</div><div class="s">Selisih nominal / data berubah</div></div>
    <div class="kpi"><div class="l">Belum cocok <span class="text-alert">@include('partials.ikon', ['n' => 'err', 'u' => 15])</span></div><div class="v">7</div><div class="s">NRP/NIP tak ditemukan</div></div>
</section>

<section class="card flex flex-col gap-3.5">
    <h2>Rekap per jenis personel</h2>
    <div>
        <div class="flex h-3 gap-0.5 overflow-hidden rounded-[3px]" role="img" aria-label="Komposisi personel: {{ $pt }} persen TNI, {{ 100 - $pt }} persen PNS">
            <div class="bg-body" style="width: {{ $pt }}%"></div>
            <div class="bg-[#B4B1A6]" style="width: {{ 100 - $pt }}%"></div>
        </div>
        <div class="mt-1.5 flex justify-between text-xs text-muted"><span>TNI {{ $pt }}%</span><span>PNS {{ 100 - $pt }}%</span></div>
    </div>
    <table class="w-full border-collapse text-[13px]">
        <thead>
            <tr class="text-muted">
                <th scope="col" class="border-b border-line py-2 text-left font-medium">Komponen</th>
                <th scope="col" class="border-b border-line py-2 text-right font-medium">TNI</th>
                <th scope="col" class="border-b border-line py-2 text-right font-medium">PNS</th>
                <th scope="col" class="border-b border-line py-2 text-right font-medium">Total</th>
            </tr>
        </thead>
        <tbody class="font-mono">
            <tr><th scope="row" class="border-b border-[#EEECE4] py-2 text-left font-sans font-normal">Personel</th><td class="border-b border-[#EEECE4] text-right">{{ $T['n'] }}</td><td class="border-b border-[#EEECE4] text-right">{{ $N['n'] }}</td><td class="border-b border-[#EEECE4] text-right">{{ $total }}</td></tr>
            <tr><th scope="row" class="border-b border-[#EEECE4] py-2 text-left font-sans font-normal">Penghasilan kotor</th><td class="border-b border-[#EEECE4] text-right">{{ juta($T['kotor']) }}</td><td class="border-b border-[#EEECE4] text-right">{{ juta($N['kotor']) }}</td><td class="border-b border-[#EEECE4] text-right">{{ juta($T['kotor'] + $N['kotor']) }}</td></tr>
            <tr><th scope="row" class="border-b border-[#EEECE4] py-2 text-left font-sans font-normal">Potongan</th><td class="border-b border-[#EEECE4] text-right">{{ juta($T['pot']) }}</td><td class="border-b border-[#EEECE4] text-right">{{ juta($N['pot']) }}</td><td class="border-b border-[#EEECE4] text-right">{{ juta($T['pot'] + $N['pot']) }}</td></tr>
            <tr class="font-semibold"><th scope="row" class="py-2 text-left font-sans font-semibold">Diterima bersih</th><td class="text-right">{{ juta($T['bersih']) }}</td><td class="text-right">{{ juta($N['bersih']) }}</td><td class="text-right">{{ juta($T['bersih'] + $N['bersih']) }}</td></tr>
        </tbody>
    </table>
</section>

<section class="card">
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2>Perlu perhatian</h2>
        <a href="{{ route('proses.show', 3) }}" class="text-[15px] font-medium">Lihat semua (26)</a>
    </div>
    <div class="overflow-x-auto">
        <table class="t min-w-[760px]">
            <thead>
                <tr>
                    <th scope="col">Nama</th><th scope="col">Pangkat / Gol.</th><th scope="col">NRP / NIP</th>
                    <th scope="col">Jenis</th><th scope="col">Masalah</th><th scope="col" class="r">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($perhatian as [$nama, $pangkat, $id, $jenis, $galat, $masalah])
                    <tr>
                        <td><b>{{ $nama }}</b></td>
                        <td>{{ $pangkat }}</td>
                        <td class="num">{{ $id }}</td>
                        <td>{{ $jenis }}</td>
                        <td>
                            @if ($galat)
                                <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $masalah }}</span>
                            @else
                                {{ $masalah }}
                            @endif
                        </td>
                        <td class="r act"><a class="lnk" href="{{ route('proses.show', $galat ? 3 : 4) }}">Tinjau</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
