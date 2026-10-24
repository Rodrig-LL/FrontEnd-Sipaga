@extends('layouts.tamu')
@section('title', 'Masuk')

@section('konten')
<section class="card w-full max-w-[400px] p-7" x-data="{ lihat: false }">
    <div class="flex items-center gap-2.5 pb-[18px]">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gold text-sm font-semibold text-side" aria-hidden="true">SG</div>
        <div>
            <b class="block text-lg tracking-[.06em]">SIPAGA</b>
            <span class="block text-xs text-muted">Slip Gaji Terpadu</span>
        </div>
    </div>
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
        <p class="muted">Prototipe: isi nama pengguna dan kata sandi apa saja.</p>
    </form>
</section>
@endsection
