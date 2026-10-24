@extends('layouts.app')
@section('title', 'Proses gaji')

@section('konten')
@include('partials.kembali', ['href' => route('gaji.index'), 'label' => 'Kembali ke Proses Gaji'])

<header>
    <p class="text-[13px] text-muted">Proses {{ $p->created_at->translatedFormat('j F Y H:i') }} WIB · {{ $p->user?->name ?? '—' }}</p>
    <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Proses gaji {{ $p->bulan_paksa }}</h1>
</header>

@if (in_array($p->status, ['menunggu', 'berjalan'], true))
    <section class="card max-w-[820px]" x-data x-init="setTimeout(() => location.reload(), 3000)" role="status">
        <h2>{{ $p->status === 'menunggu' ? 'Menunggu giliran…' : 'Sedang membaca PDF dan mencocokkan…' }}</h2>
        <p class="muted mt-1.5">Halaman ini menyegarkan diri. PDF hasil scan bisa memakan beberapa menit.</p>
    </section>
@else
    <section class="card max-w-[820px]">
        <h2 class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Proses gagal</h2>
        <p class="mt-2 whitespace-pre-line leading-normal">{{ $p->galat }}</p>
        <p class="muted mt-3">Perbaiki sesuai pesan di atas, lalu <a class="lnk" href="{{ route('gaji.index') }}">proses ulang</a>.</p>
    </section>
@endif
@endsection
