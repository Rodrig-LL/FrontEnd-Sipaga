@extends('layouts.tamu')
@section('title', 'Masuk')

@section('konten')
<div class="flex w-full max-w-[400px] flex-col items-center gap-6">
    {{-- Identitas: lambang di tengah, nama direktorat (gaya portal carakabinum.web.id), lalu nama aplikasi. --}}
    <header class="text-center">
        <div class="flex justify-center">@include('partials.logo', ['ukuran' => 'h-24 w-32', 'polos' => true])</div>
        <p class="mt-4 text-[17px] font-bold uppercase leading-tight tracking-[.02em] text-brand-dark">Direktorat Ajudan Jenderal</p>
        <p class="mt-1 text-xs font-semibold uppercase tracking-[.2em] text-muted">TNI Angkatan Darat</p>
        <p class="mt-4 text-xl font-bold tracking-[.08em] text-brand-dark">SIPAGA</p>
        <p class="mt-0.5 text-sm text-muted">Sistem Pengolahan Data dan Slip Gaji</p>
    </header>

    <section class="card w-full p-7" x-data="{ lihat: false }">
        <h1 class="mb-3.5 text-[22px] font-semibold">Masuk</h1>

        <form method="post" action="{{ route('masuk.simpan') }}" class="flex flex-col gap-3.5">
            @csrf
            <label class="fl text-sm">Nama pengguna
                <input name="pengguna" value="{{ old('pengguna') }}" class="inp h-12 text-base {{ $errors->first('pengguna') ? 'bad' : '' }}" type="text" autocomplete="username" autofocus>
                @if ($errors->first('pengguna'))<span class="ferr" role="alert">{{ $errors->first('pengguna') }}</span>@endif
            </label>
            <label class="fl text-sm">Kata sandi
                <input name="sandi" class="inp h-12 text-base {{ $errors->first('sandi') ? 'bad' : '' }}" x-bind:type="lihat ? 'text' : 'password'" type="password" autocomplete="current-password">
                @if ($errors->first('sandi'))<span class="ferr" role="alert">{{ $errors->first('sandi') }}</span>@endif
            </label>
            <label class="chk"><input type="checkbox" x-model="lihat">Tampilkan kata sandi</label>
            <button type="submit" class="btn btn-p btn-lg">Masuk</button>
        </form>
    </section>
</div>
@endsection
