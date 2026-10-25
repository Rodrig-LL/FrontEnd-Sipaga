@extends('layouts.app')
@section('title', $akun ? 'Ubah akun' : 'Tambah akun')

@php
    $e = fn ($k) => $errors->first($k);
    $diri = $akun && $akun->is(auth()->user());
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('akun.index'), 'label' => 'Kembali ke Akun Pengguna'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $akun ? $akun->name : 'Akun Pengguna' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $akun ? 'Ubah akun' : 'Tambah akun' }}</h1>
        <p class="mt-1.5 text-[15px] text-body">Perubahan akun dicatat di log aktivitas. Kata sandi tidak pernah dicatat.</p>
    </div>
</header>

<form method="post" action="{{ $akun ? route('akun.update', $akun) : route('akun.store') }}" class="flex max-w-[820px] flex-col gap-4">
    @csrf
    @if ($akun) @method('PUT') @endif

    <section class="card">
        <h2>Identitas</h2>
        <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl md:col-span-2">Nama lengkap
                <input name="name" value="{{ old('name', $akun?->name) }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e('name') ? 'bad' : '' }}">
                @if ($e('name'))<span class="ferr" role="alert">{{ $e('name') }}</span>@endif
            </label>
            <label class="fl">Nama pengguna (untuk masuk)
                <input name="username" value="{{ old('username', $akun?->username) }}" type="text" autocomplete="off" autocapitalize="off" class="inp w-full font-mono text-base {{ $e('username') ? 'bad' : '' }}">
                @if ($e('username'))<span class="ferr" role="alert">{{ $e('username') }}</span>@endif
            </label>
            <label class="fl">Peran
                <select name="role" class="inp w-full text-base {{ $e('role') ? 'bad' : '' }}" @disabled($diri)>
                    @foreach (\App\Models\User::PERAN as $kode => $label)
                        <option value="{{ $kode }}" @selected(old('role', $akun?->role ?? 'juru_bayar') === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
                @if ($diri)<input type="hidden" name="role" value="{{ $akun->role }}">@endif
                @if ($e('role'))<span class="ferr" role="alert">{{ $e('role') }}</span>@endif
            </label>
        </div>
        <p class="muted mt-3.5 leading-normal">
            <b>Admin</b>: hanya kelola akun dan lihat log aktivitas. <b>Juru Bayar</b>: seluruh pekerjaan gaji dan data personel.
        </p>
    </section>

    <section class="card">
        <h2>{{ $akun ? 'Ganti kata sandi' : 'Kata sandi' }}</h2>
        <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl">{{ $akun ? 'Kata sandi baru' : 'Kata sandi' }} <span class="muted">(minimal 8 karakter)</span>
                <input name="sandi" type="password" autocomplete="new-password" class="inp w-full text-base {{ $e('sandi') ? 'bad' : '' }}">
                @if ($e('sandi'))<span class="ferr" role="alert">{{ $e('sandi') }}</span>@endif
            </label>
            <label class="fl">Ulangi kata sandi
                <input name="sandi_confirmation" type="password" autocomplete="new-password" class="inp w-full text-base">
            </label>
        </div>
        @if ($akun)<p class="muted mt-3.5">Kosongkan bila kata sandi tidak diganti.</p>@endif
    </section>

    @if ($akun)
    <section class="card">
        <h2>Status</h2>
        <label class="chk mt-2.5"><input type="checkbox" name="aktif" value="1" @checked($diri || $akun->aktif) @disabled($diri)>Akun aktif (bisa masuk)</label>
        @if ($diri)
            <p class="muted mt-3.5">Anda tidak bisa menonaktifkan atau menurunkan peran akun Anda sendiri.</p>
        @else
            <p class="muted mt-3.5">Akun yang dinonaktifkan langsung dikeluarkan, termasuk bila sedang masuk.</p>
        @endif
    </section>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
        <a class="btn btn-o btn-lg !text-[15px] !font-medium" href="{{ route('akun.index') }}">Batal</a>
        <button type="submit" class="btn btn-p btn-lg">Simpan</button>
    </div>
</form>
@endsection
