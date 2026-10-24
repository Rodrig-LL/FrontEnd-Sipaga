@extends('layouts.app')
@section('title', 'Data Personel')

@section('konten')
@include('partials.kembali', ['href' => route('dasbor'), 'label' => 'Kembali ke Dasbor'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $jumlah[''] }} personel aktif · diperbarui 8 Okt 2026</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Data Personel</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        <span class="badge">Data contoh</span>
        <a class="btn" href="{{ route('personel.impor') }}">Impor dari Excel</a>
        <a class="btn btn-p" href="{{ route('personel.create') }}">Tambah personel</a>
    </div>
</header>

@include('partials.saring', ['opsi' => [['', 'Semua status'], ['aktif', 'Aktif'], ['kurang', 'Data belum lengkap'], ['pensiun', 'Akan pensiun']]])

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="t min-w-[960px]">
            <thead>
                <tr>
                    <th scope="col" class="pl-3.5">Nama</th><th scope="col">Pangkat / Gol.</th><th scope="col">NRP / NIP</th><th scope="col">Jenis</th>
                    <th scope="col">Bagian</th><th scope="col">Keluarga</th><th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hal as $r)
                    <tr>
                        <td class="pl-3.5"><b>{{ $r['nama'] }}</b></td>
                        <td>{{ $r['pangkat'] }}</td>
                        <td class="num">{{ $r['nrp'] }}</td>
                        <td>{{ $r['jenis'] }}</td>
                        <td>{{ $r['bagian'] }}</td>
                        <td>{{ $r['kel'] }}</td>
                        <td class="whitespace-nowrap">
                            @if ($r['kurang'])
                                <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Data belum lengkap</span>
                            @elseif ($r['pensiun'])
                                <span class="text-[#4A4D46]">Akan pensiun · {{ $r['pensiun'] }}</span>
                            @else
                                Aktif
                            @endif
                        </td>
                        <td class="r act pr-3.5"><a class="lnk" href="{{ route('personel.edit', $r['n']) }}" aria-label="{{ $r['kurang'] ? 'Lengkapi' : 'Ubah' }} data {{ $r['nama'] }}">{{ $r['kurang'] ? 'Lengkapi' : 'Ubah' }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center muted">Tidak ada personel yang cocok dengan pencarian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'personel'])
</section>
@endsection
