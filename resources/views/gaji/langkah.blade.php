@extends('layouts.app')
@section('title', 'Proses Gaji · Langkah '.$langkah)

@php
    $label = ['Unggah berkas', 'Periksa hasil baca', 'Cocokkan data', 'Verifikasi selisih', 'Unduh hasil'];
    $judul = $label[$langkah - 1];
    $bantuan = [
        2 => 'Pastikan sistem membaca PDF dengan benar sebelum lanjut.',
        3 => 'Sistem mencocokkan anggota DPP dengan pembukuan dan Data Personel, lalu mencatat temuan.',
        4 => 'Gaji yang berubah dibanding pembukuan bulan lalu. Angka dari DPP yang ditulis ke pembukuan baru.',
        5 => 'Periksa rekonsiliasi, lalu unduh pembukuan baru dan laporan validasi.',
    ][$langkah];
    $url = fn ($n) => $n === 1 ? route('gaji.index') : route('gaji.langkah', [$p, $n]);
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('gaji.index'), 'label' => 'Kembali ke Proses Gaji'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Proses gaji · {{ $p->bulan ?: '—' }} · diproses {{ $p->selesai_at?->translatedFormat('j F Y H:i') }} WIB oleh {{ $p->user?->name ?? '—' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Langkah {{ $langkah }} dari 5: {{ $judul }}</h1>
        <p class="mt-1.5 text-[15px] text-body">{{ $bantuan }}</p>
    </div>
</header>

<ol class="steps flex flex-wrap gap-1.5" aria-label="Tahapan proses">
    @foreach ($label as $i => $nama)
        @php $no = $i + 1; $kelas = $no < $langkah ? 'done' : ($no === $langkah ? 'now' : 'next'); @endphp
        <li class="{{ $no === $langkah ? 'cur' : '' }}" @if ($no === $langkah) aria-current="step" @endif>
            <a href="{{ $url($no) }}" class="flex items-center gap-2.5 text-inherit no-underline">
                <span class="dot {{ $kelas }}" aria-hidden="true">@if ($no < $langkah)@include('partials.ikon', ['n' => 'ok', 'u' => 13, 'w' => 3])@else{{ $no }}@endif</span>{{ $nama }}
            </a>
        </li>
    @endforeach
</ol>

@include('gaji.l'.$langkah)

<footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
    <a class="btn btn-o btn-lg !text-[15px] !font-medium" href="{{ $url($langkah - 1) }}">← {{ $langkah === 2 ? 'Langkah 1: unggah berkas' : 'Langkah sebelumnya' }}</a>
    @if ($langkah < 5)
        <div class="flex flex-wrap items-center gap-3">
            @if ($peringatan)<span class="muted">{{ $peringatan }}</span>@endif
            <a class="btn btn-p btn-lg" href="{{ $url($langkah + 1) }}">Lanjut: {{ $label[$langkah] }} →</a>
        </div>
    @else
        <a class="btn btn-p btn-lg" href="{{ route('gaji.index') }}">Proses baru</a>
    @endif
</footer>
@endsection
