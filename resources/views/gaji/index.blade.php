@extends('layouts.app')
@section('title', 'Proses Gaji')

@php
    $label = ['menunggu' => 'Menunggu', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai', 'gagal' => 'Gagal'];
@endphp

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Dari DPP ke pembukuan</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Proses Gaji</h1>
        <p class="mt-1.5 text-[15px] text-body">Unggah PDF DPP bulan ini dan pembukuan bulan lalu. Hasilnya: pembukuan baru dan laporan validasi.</p>
    </div>
</header>

@unless ($pythonAda)
    <p class="note" role="alert"><b>Python belum terpasang untuk aplikasi ini.</b> Jalankan:
        <code>python3 -m venv python/.venv &amp;&amp; python/.venv/bin/pip install -r python/requirements.txt</code></p>
@endunless

@if ($bolehJalan)
    <section class="card max-w-[820px]">
        <h2>Proses baru</h2>
        <form method="post" action="{{ route('gaji.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-4">
            @csrf
            <label class="fl">PDF DPP bulan ini <span class="muted">(satu atau lebih, dari satu bank)</span>
                <input type="file" name="pdf[]" multiple accept=".pdf,application/pdf" class="inp h-auto py-2 text-sm {{ $errors->has('pdf') || $errors->has('pdf.*') ? 'bad' : '' }}">
                @if ($errors->has('pdf'))<span class="ferr" role="alert">{{ $errors->first('pdf') }}</span>@endif
                @foreach ($errors->get('pdf.*') as $m)<span class="ferr" role="alert">{{ $m[0] }}</span>@endforeach
            </label>
            <label class="fl">Pembukuan bulan lalu <span class="muted">(.xlsx; nama bank, mis. BRI, dibaca dari nama berkas)</span>
                <input type="file" name="buku" accept=".xlsx" class="inp h-auto py-2 text-sm {{ $errors->has('buku') ? 'bad' : '' }}">
                @if ($errors->has('buku'))<span class="ferr" role="alert">{{ $errors->first('buku') }}</span>@endif
            </label>
            <label class="fl max-w-[280px]">Bulan <span class="muted">(opsional)</span>
                <input name="bulan" value="{{ old('bulan') }}" placeholder="NOVEMBER 2026" class="inp {{ $errors->has('bulan') ? 'bad' : '' }}" autocomplete="off">
                @if ($errors->has('bulan'))<span class="ferr" role="alert">{{ $errors->first('bulan') }}</span>@endif
            </label>
            <p class="muted">Dicocokkan dengan {{ $tniAktif }} personel TNI aktif di Data Personel. Berkas yang diunggah disimpan di server ini saja.</p>
            <div><button type="submit" class="btn btn-p btn-lg" @disabled(! $pythonAda)>Proses berkas</button></div>
        </form>
    </section>
@else
    <p class="note">Hanya Admin dan Juru Bayar yang bisa menjalankan proses gaji. Anda tetap bisa melihat hasilnya di bawah.</p>
@endif

<section class="card overflow-hidden p-0">
    <h2 class="px-3.5 pt-3.5">Proses sebelumnya</h2>
    <div class="overflow-x-auto">
        <table class="t min-w-[640px]">
            <thead><tr><th scope="col" class="pl-3.5">Waktu</th><th scope="col">Bulan</th><th scope="col">Oleh</th><th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th></tr></thead>
            <tbody>
                @forelse ($daftar as $d)
                    <tr>
                        <td class="whitespace-nowrap pl-3.5">{{ $d->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td>{{ $d->bulan ?: '—' }}</td>
                        <td>{{ $d->user?->name ?? '—' }}</td>
                        <td>@if ($d->status === 'gagal')<span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $label[$d->status] }}</span>@else{{ $label[$d->status] ?? $d->status }}@endif</td>
                        <td class="r act pr-3.5"><a class="lnk" href="{{ route('gaji.show', $d) }}">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center muted">Belum ada proses.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
