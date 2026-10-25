@extends('layouts.app')
@section('title', 'Akun Pengguna')

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $daftar->where('aktif', true)->count() }} akun aktif dari {{ $daftar->count() }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Akun Pengguna</h1>
        <p class="mt-1.5 text-[15px] text-body">Nonaktifkan akun untuk menghentikan akses sementara; hapus bila akun memang tidak dipakai lagi.</p>
    </div>
    <a class="btn btn-p" href="{{ route('akun.create') }}">Tambah akun</a>
</header>

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="t min-w-[720px]">
            <thead>
                <tr><th scope="col" class="pl-3.5">Nama</th><th scope="col">Nama pengguna</th><th scope="col">Peran</th><th scope="col">Status</th><th scope="col">Dibuat</th><th scope="col" class="r pr-3.5">Aksi</th></tr>
            </thead>
            <tbody>
                @foreach ($daftar as $a)
                    <tr>
                        <td class="pl-3.5"><b>{{ $a->name }}</b>@if ($a->is(auth()->user())) <span class="muted">(Anda)</span>@endif</td>
                        <td class="num">{{ $a->username }}</td>
                        <td>{{ \App\Models\User::PERAN[$a->role] ?? $a->role }}</td>
                        <td>@if ($a->aktif) Aktif @else <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Nonaktif</span> @endif</td>
                        <td class="whitespace-nowrap">{{ $a->created_at?->translatedFormat('j M Y') }}</td>
                        <td class="r act pr-3.5"><a class="lnk" href="{{ route('akun.edit', $a) }}" aria-label="Ubah akun {{ $a->name }}">Ubah</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
