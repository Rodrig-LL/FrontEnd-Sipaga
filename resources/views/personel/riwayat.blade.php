@extends('layouts.app')
@section('title', 'Riwayat perubahan personel')

@php
    $kolom = ['jenis' => 'Jenis', 'nama' => 'Nama', 'pangkat' => 'Pangkat/gol.', 'nrp_asli' => 'NRP/NIP', 'jabatan' => 'Jabatan', 'ket' => 'KET',
        'seksi' => 'Seksi', 'tgl_lahir' => 'Tgl lahir', 'bank' => 'Bank', 'rekening' => 'No rekening', 'nama_rekening' => 'Nama rekening',
        'status' => 'Status', 'tgl_keluar' => 'Tgl keluar', 'alasan_keluar' => 'Alasan keluar', 'tandai' => 'Tanda'];
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('personel.index'), 'label' => 'Kembali ke Data Personel'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $personel ? $personel->nama : 'Semua personel' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Riwayat perubahan</h1>
        <p class="mt-1.5 text-[15px] text-body">Catatan ini hanya bisa ditambah; tidak ada yang bisa mengubah atau menghapusnya.</p>
    </div>
</header>

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="t min-w-[860px]">
            <thead>
                <tr><th scope="col" class="pl-3.5">Waktu</th><th scope="col">Pengguna</th><th scope="col">Aksi</th><th scope="col">Personel</th><th scope="col" class="pr-3.5">Perubahan</th></tr>
            </thead>
            <tbody>
                @forelse ($hal as $h)
                    <tr>
                        <td class="whitespace-nowrap pl-3.5">{{ $h->waktu->translatedFormat('j M Y H:i') }}</td>
                        <td>{{ $h->operator }}</td>
                        <td><b>{{ $h->aksi }}</b></td>
                        <td>{{ $h->nama }}<div class="sm num">{{ $h->nrp }}</div></td>
                        <td class="pr-3.5">
                            @foreach ($h->perubahan as $k => [$lama, $baru])
                                <div class="text-sm"><span class="text-muted">{{ $kolom[$k] ?? $k }}:</span>
                                    @if ($lama !== '')<s>{{ $lama }}</s> → @endif{{ $baru !== '' ? $baru : '(kosong)' }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center muted">Belum ada catatan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'catatan'])
</section>
@endsection
