@extends('layouts.app')
@section('title', 'Data Personel')

@section('konten')
@include('partials.kembali', ['href' => route('dashboard'), 'label' => 'Kembali ke Dashboard'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $aktif }} personel aktif{{ $diubah ? ' · diperbarui '.\Illuminate\Support\Carbon::parse($diubah)->translatedFormat('j M Y') : '' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Data Personel</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        <a class="btn" href="{{ route('personel.impor') }}">Impor dari Excel</a>
        <a class="btn" href="{{ route('personel.riwayat') }}">Riwayat perubahan</a>
        <a class="btn btn-p" href="{{ route('personel.create') }}">Tambah personel</a>
    </div>
</header>

@include('partials.saring', ['opsi' => [['', 'Semua status'], ['aktif', 'Aktif'], ['keluar', 'Sudah keluar'], ['tandai', 'Perlu dicek'], ['tanpa_bank', 'Aktif, bank belum diisi']]])

<section class="card overflow-hidden p-0">
    <div class="overflow-x-auto">
        <table class="t min-w-[960px]">
            <thead>
                <tr>
                    @include('partials.th-urut', ['kode' => 'nama', 'label' => 'Nama', 'bawaan' => 'naik', 'kelas' => 'pl-3.5'])
                    @include('partials.th-urut', ['kode' => 'pangkat', 'label' => 'Pangkat / Gol.', 'bawaan' => 'turun'])
                    <th scope="col">NRP / NIP</th>
                    @include('partials.th-urut', ['kode' => 'jenis', 'label' => 'Jenis', 'bawaan' => 'naik'])
                    <th scope="col">Jabatan</th><th scope="col">Bank</th><th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($hal as $r)
                    <tr>
                        <td class="pl-3.5"><b>{{ $r->nama }}</b></td>
                        <td>{{ $r->pangkat ?: '—' }}</td>
                        <td class="num">{{ $r->nrp_asli ?: $r->nrp }}</td>
                        <td>{{ $r->jenis }}</td>
                        <td>{{ $r->jabatan ?: '—' }}</td>
                        <td>{{ $r->bank ?: '—' }}</td>
                        <td class="whitespace-nowrap">
                            @if ($r->status === 'keluar')
                                <span class="text-[#4A4D46]">Keluar · {{ $r->tgl_keluar?->translatedFormat('j M Y') }}</span>
                            @elseif ($r->tandai !== '')
                                <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $r->tandai }}</span>
                            @elseif ($r->bank === '')
                                <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Bank belum diisi</span>
                            @else
                                Aktif
                            @endif
                        </td>
                        <td class="r act pr-3.5"><a class="lnk" href="{{ route('personel.edit', $r) }}" aria-label="Ubah data {{ $r->nama }}">Ubah</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center muted">{{ $jumlah[''] === 0 ? 'Belum ada personel. Klik “Tambah personel”.' : 'Tidak ada personel yang cocok dengan pencarian.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'personel'])
</section>
@endsection
