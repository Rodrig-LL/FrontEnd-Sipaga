@extends('layouts.app')
@section('title', 'Slip Gaji')

@section('konten')
@include('partials.kembali', ['href' => route('dasbor'), 'label' => 'Kembali ke Dasbor'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Periode gaji · {{ session('periode', 'Oktober 2026') }} · 486 siap terbit</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Slip Gaji</h1>
    </div>
    <div class="flex flex-wrap items-end gap-2.5">
        <span class="badge">Data contoh</span>
        <a class="btn" href="{{ route('slip.cetak', ['semua' => 1]) }}">Cetak / PDF semua slip</a>
    </div>
</header>

@include('partials.saring', ['opsi' => [['', 'Semua status'], ['siap', 'Siap terbit'], ['verif', 'Perlu dicek'], ['cetak', 'Sudah dicetak']]])

<section class="card overflow-hidden p-0">
    {{-- Daftar centang: dikirim lewat GET ke halaman cetak. Alpine hanya untuk menghitung yang dicentang. --}}
    <form method="get" action="{{ route('slip.cetak') }}" id="pilih"
          x-data="{ n: 0,
                    hitung() { this.n = this.$root.querySelectorAll('input[name=\'ids[]\']:checked').length },
                    semua(e) { this.$root.querySelectorAll('input[name=\'ids[]\']').forEach(c => c.checked = e.target.checked); this.hitung() },
                    batal() { this.$root.querySelectorAll('input[name=\'ids[]\']').forEach(c => c.checked = false); this.$root.querySelector('#semua').checked = false; this.hitung() } }"
          x-on:change="hitung()">
        <div class="flex flex-wrap items-center justify-between gap-2.5 border-b border-[#BDBAB0] px-3.5 py-2.5">
            <span class="text-[15px] font-semibold" x-text="n ? n + ' slip dipilih' : 'Belum ada slip dipilih'">Belum ada slip dipilih</span>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn" x-on:click="batal()" x-bind:disabled="!n" disabled>Batal pilih</button>
                <button type="submit" class="btn btn-p" x-bind:disabled="!n" disabled>Cetak / simpan PDF <span x-text="n ? '(' + n + ' slip)' : ''"></span></button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="t min-w-[960px]">
                <thead>
                    <tr>
                        <th scope="col" class="w-11 pl-3.5"><input type="checkbox" id="semua" class="h-[18px] w-[18px]" aria-label="Pilih semua di halaman ini" x-on:change.stop="semua($event)"></th>
                        <th scope="col">Nama</th><th scope="col">Pangkat / Gol.</th><th scope="col">Jenis</th>
                        <th scope="col" class="r">Penghasilan</th><th scope="col" class="r">Potongan</th><th scope="col" class="r">Diterima</th>
                        <th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($hal as $r)
                        <tr>
                            <td class="w-11 pl-3.5"><input type="checkbox" name="ids[]" value="{{ $r['n'] }}" class="h-[18px] w-[18px]" aria-label="Pilih slip {{ $r['nama'] }}"></td>
                            <td><b>{{ $r['nama'] }}</b></td>
                            <td>{{ $r['pangkat'] }}</td>
                            <td>{{ $r['jenis'] }}</td>
                            <td class="r num">{{ rp($r['kotor']) }}</td>
                            <td class="r num">{{ rp($r['pot']) }}</td>
                            <td class="r num"><b>{{ rp($r['bersih']) }}</b></td>
                            <td class="whitespace-nowrap">
                                @if ($r['stat'] === 'verif')
                                    <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Perlu dicek</span>
                                @elseif ($r['stat'] === 'cetak')
                                    <span class="text-muted">Sudah dicetak</span>
                                @else
                                    Siap terbit
                                @endif
                            </td>
                            <td class="r act pr-3.5"><a class="lnk" href="{{ route('slip.show', $r['n']) }}">Lihat</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-6 text-center muted">Tidak ada slip yang cocok dengan pencarian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>
    @include('partials.pager', ['label' => 'slip'])
</section>
@endsection
