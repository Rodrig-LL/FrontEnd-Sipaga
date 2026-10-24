@extends('layouts.app')
@section('title', 'Proses Gaji · Langkah '.$langkah)

@section('konten')
@include('partials.kembali', ['href' => route('dasbor'), 'label' => 'Kembali ke Dasbor'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Proses gaji · Oktober 2026 · kemajuan tersimpan otomatis</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Langkah {{ $langkah }} dari 5: {{ $judul }}</h1>
        <p class="mt-1.5 text-[15px] text-body">{{ $bantuan }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2.5">
        <span class="badge">Data contoh</span>
        <form method="post" action="{{ route('proses.aksi') }}">
            @csrf
            <input type="hidden" name="do" value="reset">
            <button type="submit" class="btn-link">Mulai ulang (uji coba)</button>
        </form>
    </div>
</header>

<ol class="steps flex flex-wrap gap-1.5" aria-label="Tahapan proses">
    @foreach ($label as $i => $nama)
        @php $no = $i + 1; $kelas = $no < $langkah ? 'done' : ($no === $langkah ? 'now' : 'next'); @endphp
        <li class="{{ $no === $langkah ? 'cur' : '' }}" @if ($no === $langkah) aria-current="step" @endif>
            <span class="dot {{ $kelas }}" aria-hidden="true">@if ($no < $langkah)@include('partials.ikon', ['n' => 'ok', 'u' => 13, 'w' => 3])@else{{ $no }}@endif</span>{{ $nama }}
        </li>
    @endforeach
</ol>

@include('proses.langkah'.$langkah)

<footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
    @if ($langkah > 1)
        <a class="btn btn-o btn-lg !text-[15px] !font-medium" href="{{ route('proses.show', $langkah - 1) }}">← Langkah sebelumnya</a>
    @else
        <span class="btn btn-o btn-lg off !text-[15px] !font-medium" aria-disabled="true">← Langkah sebelumnya</span>
    @endif

    @if ($langkah < 5)
        <div class="flex flex-wrap items-center gap-3">
            @if ($peringatan)<span class="muted">{{ $peringatan }}</span>@endif
            <a class="btn btn-p btn-lg" href="{{ route('proses.show', $langkah + 1) }}">Lanjut: {{ $label[$langkah] }} →</a>
        </div>
    @endif
</footer>
@endsection
