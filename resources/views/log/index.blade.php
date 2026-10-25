@extends('layouts.app')
@section('title', 'Log Aktivitas')

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ number_format($total, 0, ',', '.') }} catatan</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Log Aktivitas</h1>
        <p class="mt-1.5 text-[15px] text-body">Catatan ini hanya bisa ditambah; tidak ada yang bisa mengubah atau menghapusnya.</p>
    </div>
</header>

<section class="filters" aria-label="Saring log">
    <form method="get" class="flex flex-1 flex-wrap items-end gap-3">
        <label class="fl min-w-[220px] flex-1">Cari
            <input class="inp" type="search" name="q" value="{{ $q }}" placeholder="Nama, nama pengguna, keterangan, atau IP" autocomplete="off">
        </label>
        <label class="fl">Aktivitas
            <select class="inp min-w-[200px]" name="aksi" x-data x-on:change="$el.form.submit()">
                <option value="">Semua aktivitas</option>
                @foreach (\App\Models\LogAktivitas::AKSI as $kode => $label)
                    <option value="{{ $kode }}" @selected($aksi === $kode)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-p h-11">Cari</button>
    </form>
</section>

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="t min-w-[860px]">
            <thead>
                <tr><th scope="col" class="pl-3.5">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aktivitas</th><th scope="col">Keterangan</th><th scope="col" class="pr-3.5">IP</th></tr>
            </thead>
            <tbody>
                @forelse ($hal as $l)
                    <tr>
                        <td class="whitespace-nowrap pl-3.5">{{ $l->waktu->translatedFormat('j M Y H:i:s') }}</td>
                        <td>@if ($l->pengguna !== '')<b>{{ $l->pengguna }}</b>@else<span class="muted">—</span>@endif<div class="sm num">{{ $l->username }}</div></td>
                        <td class="whitespace-nowrap">@if ($l->aksi === 'masuk_gagal' || $l->aksi === 'gaji_gagal')<span class="alert-t">{{ \App\Models\LogAktivitas::AKSI[$l->aksi] ?? $l->aksi }}</span>@else{{ \App\Models\LogAktivitas::AKSI[$l->aksi] ?? $l->aksi }}@endif</td>
                        <td>{{ $l->keterangan !== '' ? $l->keterangan : '—' }}</td>
                        <td class="num pr-3.5">{{ $l->ip !== '' ? $l->ip : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center muted">{{ $total === 0 ? 'Belum ada catatan.' : 'Tidak ada catatan yang cocok.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'catatan'])
</section>
@endsection
