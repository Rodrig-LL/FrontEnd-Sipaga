@extends('layouts.app')
@section('title', 'Impor personel')

@section('konten')
@include('partials.kembali', ['href' => route('personel.index'), 'label' => 'Kembali ke Data Personel'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Data Personel</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Impor personel</h1>
        <p class="mt-1.5 text-[15px] text-body">Tambahkan banyak personel sekaligus dari berkas CSV.</p>
    </div>
    <span class="badge">Data contoh</span>
</header>

<section class="card max-w-[820px]">
    <h2>1. Siapkan berkas</h2>
    <p class="muted my-1.5 mb-3 leading-normal">Kolom yang dibutuhkan: <b>nama, jenis, pangkat, nrp, bagian, anak</b>. Dari Excel, pilih Simpan Sebagai → CSV. Berkas .xlsx belum bisa dibaca langsung.</p>
    <a class="btn" href="{{ route('personel.templat') }}">Unduh templat (CSV)</a>
</section>

<section class="card max-w-[820px]">
    <h2>2. Pilih berkas</h2>
    <form method="post" action="{{ route('personel.impor.baca') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2.5">
        @csrf
        <label class="fl min-w-[260px] flex-1">Berkas CSV
            <input type="file" name="berkas" accept=".csv,.txt,.xlsx,.xls" class="inp h-auto py-2 text-sm {{ $errors->first('berkas') ? 'bad' : '' }}">
            @if ($errors->first('berkas'))<span class="ferr" role="alert">{{ $errors->first('berkas') }}</span>@endif
        </label>
        <button type="submit" class="btn btn-p h-11">Periksa berkas</button>
        <button type="submit" name="contoh" value="1" class="btn h-11">Pakai berkas contoh</button>
    </form>
</section>

@if ($hasil)
    @if (isset($hasil['galat']))
        <section class="card max-w-[820px]">
            <h2>{{ $hasil['nama'] }}</h2>
            <p class="alert-t mt-2">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $hasil['galat'] }}</p>
        </section>
    @else
        @php
            $baris = $hasil['rows'];
            $siap = count(array_filter($baris, fn ($r) => $r['masalah'] === ''));
            $bermasalah = count($baris) - $siap;
        @endphp
        <section class="grid gap-3 md:grid-cols-3">
            <div class="kpi"><div class="l">Baris terbaca</div><div class="v !text-[28px]">{{ count($baris) }}</div><div class="s">{{ $hasil['nama'] }}</div></div>
            <div class="kpi"><div class="l">Siap ditambahkan</div><div class="v !text-[28px]">{{ $siap }}</div><div class="s">Lolos pemeriksaan</div></div>
            <div class="kpi {{ $bermasalah ? 'border-ink' : '' }}"><div class="l {{ $bermasalah ? 'alert-t' : '' }}">@if ($bermasalah)@include('partials.ikon', ['n' => 'err', 'u' => 16])@endif Bermasalah</div><div class="v !text-[28px]">{{ $bermasalah }}</div><div class="s">Tidak ikut ditambahkan</div></div>
        </section>

        <section class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="t min-w-[820px]">
                    <thead><tr><th scope="col" class="pl-3.5">Baris</th><th scope="col">Nama</th><th scope="col">Jenis</th><th scope="col">Pangkat / Gol.</th><th scope="col">NRP / NIP</th><th scope="col">Hasil pemeriksaan</th></tr></thead>
                    <tbody>
                        @foreach (array_slice($baris, 0, 40) as $r)
                            <tr>
                                <td class="num pl-3.5">{{ $r['baris'] }}</td>
                                <td><b>{{ $r['nama'] }}</b></td>
                                <td>{{ $r['jenis'] }}</td>
                                <td>{{ $r['pangkat'] ?? $r['pangkat_in'] }}</td>
                                <td class="num">{{ $r['nrp'] }}</td>
                                <td>
                                    @if ($r['masalah'] !== '')
                                        <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $r['masalah'] }}</span>
                                    @else
                                        <span class="ok-t">@include('partials.ikon', ['n' => 'ok', 'u' => 16, 'w' => 2.6])Siap ditambahkan</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($baris) > 40)<p class="muted px-3.5 py-3">Menampilkan 40 baris pertama dari {{ count($baris) }}.</p>@endif
        </section>

        <form method="post" action="{{ route('personel.impor.terapkan') }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
            @csrf
            <span class="muted">{{ $bermasalah ? 'Baris bermasalah dilewati. Perbaiki di berkas lalu unggah ulang bila perlu.' : '' }}</span>
            <button type="submit" class="btn btn-p btn-lg" @disabled($siap === 0)>Tambahkan {{ $siap }} personel</button>
        </form>
    @endif
@endif
@endsection
