@extends('layouts.app')
@section('title', 'Dashboard')

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Periode gaji · {{ \App\Support\DataGaji::ada() ? \App\Support\DataGaji::bulan() : 'belum ada' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Dashboard</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        @include('partials.periode')
        <a class="btn btn-p" href="{{ route('gaji.index') }}">{{ $ada ? 'Proses gaji baru' : 'Mulai proses gaji' }}</a>
    </div>
</header>

@if (! $ada)
    @include('partials.belum-ada', ['judul' => 'Dashboard'])
@else
@php
    $t = $agg['tot'];
    $files = $agg['A'];
    $kolom = count($files) > 1 && count($files) <= 5 ? array_keys($files) : [];
    $s = $hasil['statistik'];
@endphp
<section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan">
    <div class="kpi"><div class="l">Anggota menurut DPP</div><div class="v">{{ $total }}</div><div class="s">dari {{ count($files) }} berkas PDF</div></div>
    <div class="kpi"><div class="l">Tanpa temuan</div><div class="v">{{ $siap }}</div><div class="s">{{ $total ? number_format($siap / $total * 100, 1, ',', '.') : 0 }}% dari {{ $total }} anggota</div></div>
    <div class="kpi {{ $total - $siap ? 'border-alert' : '' }}"><div class="l {{ $total - $siap ? 'alert-t' : '' }}">Perlu dicek</div><div class="v">{{ $total - $siap }}</div><div class="s">Hitungan atau temuan pada anggota</div></div>
    <div class="kpi {{ $s['dpp_tanpa_buku'] ? 'border-alert' : '' }}"><div class="l {{ $s['dpp_tanpa_buku'] ? 'alert-t' : '' }}">Belum ada di pembukuan</div><div class="v">{{ $s['dpp_tanpa_buku'] }}</div><div class="s">{{ $s['tidak_di_dpp'] }} di pembukuan tidak ada di DPP</div></div>
</section>

<section class="card flex flex-col gap-3.5">
    <h2>Rekap per berkas DPP</h2>
    <div class="overflow-x-auto">
    <table class="w-full min-w-[480px] border-collapse text-[13px]">
        <thead>
            <tr class="text-muted">
                <th scope="col" class="border-b border-line py-2 text-left font-medium">Komponen</th>
                @foreach ($kolom as $f)<th scope="col" class="border-b border-line py-2 text-right font-medium" title="{{ $f }}">{{ \Illuminate\Support\Str::limit($f, 22) }}</th>@endforeach
                <th scope="col" class="border-b border-line py-2 text-right font-medium">Total</th>
            </tr>
        </thead>
        <tbody class="font-mono">
            @foreach ([['Anggota', 'n', false], ['Penghasilan kotor', 'kotor', true], ['Potongan', 'pot', true]] as [$lbl, $k, $jt])
                <tr><th scope="row" class="border-b border-[#EEECE4] py-2 text-left font-sans font-normal">{{ $lbl }}</th>
                    @foreach ($kolom as $f)<td class="border-b border-[#EEECE4] text-right">{{ $jt ? juta($files[$f][$k]) : $files[$f][$k] }}</td>@endforeach
                    <td class="border-b border-[#EEECE4] text-right">{{ $jt ? juta($t[$k]) : $t[$k] }}</td></tr>
            @endforeach
            <tr class="font-semibold"><th scope="row" class="py-2 text-left font-sans font-semibold">Jumlah dibayarkan</th>
                @foreach ($kolom as $f)<td class="text-right">{{ juta($files[$f]['bersih']) }}</td>@endforeach
                <td class="text-right">{{ juta($t['bersih']) }}</td></tr>
        </tbody>
    </table>
    </div>
    <p class="muted">Menurut DPP. Potongan satuan (koperasi, Persit, dan lain-lain) belum termasuk.</p>
</section>

<section class="card">
    <div class="mb-3 flex items-baseline justify-between gap-3">
        <h2>Perlu perhatian</h2>
        @if ($tinggi > count($perhatian))<a href="{{ route('gaji.langkah', [$p, 3, 'lihat' => 'temuan', 'tingkat' => 'TINGGI']) }}" class="text-[15px] font-medium">Lihat semua ({{ $tinggi }})</a>@endif
    </div>
    <div class="overflow-x-auto">
        <table class="t min-w-[760px]">
            <thead><tr><th scope="col">Nama</th><th scope="col">NRP</th><th scope="col">Kategori</th><th scope="col">Keterangan</th><th scope="col" class="r">Aksi</th></tr></thead>
            <tbody>
                @forelse ($perhatian as $x)
                    <tr>
                        <td><b>{{ $x['nama'] ?: '—' }}</b></td>
                        <td class="num">{{ $x['nrp'] ?: '—' }}</td>
                        <td><span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $x['kategori'] }}</span></td>
                        <td>{{ $x['detail'] }}</td>
                        <td class="r act"><a class="lnk" href="{{ route('gaji.langkah', [$p, 3, 'lihat' => 'temuan', 'tingkat' => 'TINGGI']) }}">Tinjau</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center muted">Tidak ada temuan tinggi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endif
@endsection
