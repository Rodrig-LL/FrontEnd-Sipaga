@extends('layouts.app')
@section('title', 'Potongan Satuan')

@php
    $tab = fn ($k, $l) => '<a href="'.route('potongan.index', ['lihat' => $k]).'" aria-current="'.($lihat === $k ? 'true' : 'false').'">'.$l.'</a>';
    $rp = fn ($v) => \App\Services\PotonganService::rp($v) ?: '0';
@endphp

@section('konten')
<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Koperasi, cicilan bank, iuran, dan arisan di luar DPP</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Potongan Satuan</h1>
        <p class="mt-1.5 text-[15px] text-body">Dihitung setiap kali gaji diproses, lalu ditulis ke berkas <b>Daftar Potongan</b> (bukan ke pembukuan).</p>
    </div>
    @can('impor-personel')
        <div class="flex flex-wrap items-end gap-2.5"><a class="btn" href="{{ route('personel.impor') }}">Impor dari Excel</a></div>
    @endcan
</header>

@if ($satuan)
    @php $s = $satuan['ringkas']; @endphp
    <section class="card">
        <div class="flex flex-wrap items-baseline justify-between gap-3">
            <h2>Daftar Potongan terakhir · {{ mb_convert_case($satuan['bulan'], MB_CASE_TITLE) }} · {{ $satuan['bank'] }}</h2>
            <a class="lnk" href="{{ route('gaji.langkah', [$proses, 5]) }}">Unduh di Proses Gaji</a>
        </div>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="kpi"><div class="l">Orang dihitung</div><div class="v">{{ $s['dihitung'] }}</div><div class="s">total potongan {{ rp($s['total']) }}</div></div>
            <div class="kpi {{ $s['tunggakan'] ? 'border-alert' : '' }}"><div class="l {{ $s['tunggakan'] ? 'alert-t' : '' }}">Tunggakan koperasi</div><div class="v !text-[24px]">{{ rp($s['tunggakan']) }}</div><div class="s">{{ $s['orang_tunggakan'] }} orang, hanya dicatat</div></div>
            <div class="kpi {{ $s['minus'] ? 'border-alert' : '' }}"><div class="l {{ $s['minus'] ? 'alert-t' : '' }}">Gaji masih minus</div><div class="v">{{ $s['minus'] }}</div><div class="s">setelah koperasi nol</div></div>
            <div class="kpi"><div class="l">MASS DEBET</div><div class="v !text-[24px]">{{ rp($s['mass_debet']) }}</div><div class="s">{{ $s['mass_debet_baris'] }} baris{{ $s['tanpa_rekening'] ? ', '.$s['tanpa_rekening'].' tanpa rekening' : '' }}</div></div>
        </div>
        @if (! empty($satuan['peringatan']))
            <details class="mt-3"><summary class="cursor-pointer font-semibold">{{ count($satuan['peringatan']) }} peringatan</summary>
                <ul class="mt-2 flex flex-col gap-2 text-sm">
                    @foreach ($satuan['peringatan'] as $pw)
                        <li class="{{ $pw['tingkat'] === 'TINGGI' ? 'alert-t' : '' }}"><b>{{ $pw['kategori'] }}</b>{{ $pw['nama'] ? ' · '.$pw['nama'] : '' }}{{ $pw['letak'] ? ' · '.$pw['letak'] : '' }}<br><span class="muted">{{ $pw['detail'] }}</span></li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
@else
    <p class="note">Daftar Potongan dibuat setiap proses gaji. Isi potongan tiap orang di sini (atau impor dari pembukuan bulan lalu), lalu <a class="lnk" href="{{ route('gaji.index') }}">proses gaji</a>.</p>
@endif

<div class="fl" role="group" aria-label="Tampilan">
    <div class="seg w-fit">{!! $tab('orang', 'Per orang').$tab('pinjaman', 'Pinjaman').$tab('aturan', 'Aturan') !!}</div>
</div>

@if ($lihat === 'orang')
    <section class="filters" aria-label="Cari">
        <form method="get" class="flex flex-1 flex-wrap items-end gap-3">
            <label class="fl min-w-[220px] flex-1">Cari
                <input class="inp" type="search" name="q" value="{{ $q }}" placeholder="Nama, NRP, atau NIP" autocomplete="off">
            </label>
            <button type="submit" class="btn btn-p h-11">Cari</button>
        </form>
    </section>
    <section class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="t min-w-[760px]">
                <thead><tr><th scope="col" class="pl-3.5">Nama</th><th scope="col">NRP / NIP</th><th scope="col" class="r">Jenis potongan</th><th scope="col" class="r">Total tetap</th><th scope="col" class="r">Pinjaman aktif</th><th scope="col" class="r pr-3.5">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($hal as $r)
                        <tr>
                            <td class="pl-3.5"><b>{{ $r['p']->nama }}</b><div class="sm">{{ $r['p']->jenis }} · {{ $r['p']->pangkat ?: '—' }}</div></td>
                            <td class="num">{{ $r['p']->nrp_asli ?: $r['p']->nrp }}</td>
                            <td class="r num">{{ $r['n'] ?: '—' }}</td>
                            <td class="r num">{{ $r['total'] ? rp($r['total']) : '—' }}</td>
                            <td class="r num">{{ $r['pinjaman'] ?: '—' }}</td>
                            <td class="r act pr-3.5"><a class="lnk" href="{{ route('potongan.orang', $r['p']) }}">{{ $boleh ? 'Ubah' : 'Lihat' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-6 text-center muted">Tidak ada personel aktif yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['label' => 'personel'])
    </section>
@elseif ($lihat === 'pinjaman')
    @if ($tanpaTenor)
        <p class="note"><span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])<b>{{ $tanpaTenor }} pinjaman belum punya tenor.</b></span> Selama kosong, cicilannya tetap dipotong tiap bulan. Isi sisa tenornya lewat "Ubah".</p>
    @endif
    <section class="card overflow-hidden p-0">
        <div class="overflow-x-auto">
            <table class="t min-w-[860px]">
                <thead><tr><th scope="col" class="pl-3.5">Personel</th><th scope="col">Pemberi</th><th scope="col" class="r">Cicilan</th><th scope="col">Mulai</th><th scope="col" class="r">Tenor</th><th scope="col">Keadaan {{ \App\Support\PotonganData::teksPeriode($periode) }}</th><th scope="col" class="r pr-3.5">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($hal as $r)
                        @php $x = $r['pj']; @endphp
                        <tr>
                            <td class="pl-3.5"><b>{{ $x->personel->nama }}</b><div class="sm num">{{ $x->personel->nrp_asli ?: $x->personel->nrp }}</div></td>
                            <td>{{ \App\Support\PotonganData::label($x->pemberi) }}</td>
                            <td class="r num">{{ rp($x->cicilan) }}</td>
                            <td>{{ \App\Support\PotonganData::teksPeriode($x->mulai) }}</td>
                            <td class="r num">@if ($x->tenor === null && $x->status === 'aktif')<span class="alert-t">belum diisi</span>@else{{ $x->tenor ? $x->tenor.' bln' : '—' }}@endif</td>
                            <td>{{ $r['keadaan']['ket'] }}</td>
                            <td class="r act pr-3.5"><a class="lnk" href="{{ route('potongan.orang', ['personel' => $x->personel_id, 'pinjaman' => $x->id]) }}">{{ $boleh ? 'Ubah' : 'Lihat' }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-6 text-center muted">Belum ada pinjaman. Catat dari halaman per orang, atau impor dari pembukuan bulan lalu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['label' => 'pinjaman'])
    </section>
@else
    <form method="post" action="{{ route('potongan.aturan') }}" class="flex max-w-[900px] flex-col gap-4">
        @csrf
        <section class="card">
            <h2>Umum</h2>
            <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
                <label class="fl">Sisa gaji minimal (YBS), Rp
                    <input name="sisa_ybs" value="{{ old('sisa_ybs', $aturan['sisa_ybs']) }}" class="inp w-full font-mono text-base {{ $errors->has('sisa_ybs') ? 'bad' : '' }}" inputmode="numeric" @disabled(! $boleh)>
                    @if ($errors->has('sisa_ybs'))<span class="ferr" role="alert">{{ $errors->first('sisa_ybs') }}</span>@endif
                    <span class="muted">Bila potongan melebihi gaji, orang ini disisakan segini; Koperasi Ditajenad yang menunggak.</span>
                </label>
                <label class="fl">Simpanan pokok bawaan, Rp
                    <input name="pokok" value="{{ old('pokok', $aturan['pokok']) }}" class="inp w-full font-mono text-base {{ $errors->has('pokok') ? 'bad' : '' }}" inputmode="numeric" @disabled(! $boleh)>
                    @if ($errors->has('pokok'))<span class="ferr" role="alert">{{ $errors->first('pokok') }}</span>@endif
                    <span class="muted">Dipotong sekali di bulan pertama keanggotaan koperasi.</span>
                </label>
            </div>
        </section>
        <section class="card overflow-hidden p-0">
            <h2 class="px-3.5 pt-3.5">Batas pinjam per pemberi</h2>
            <p class="muted px-3.5 pb-2 pt-1">Kosong = tanpa batas. Melewati batas hanya menjadi peringatan.</p>
            <div class="overflow-x-auto">
                <table class="t min-w-[640px]">
                    <thead><tr><th scope="col" class="pl-3.5">Pemberi</th><th scope="col">Maks % gaji DPP</th><th scope="col">Sisa gaji minimal (Rp)</th><th scope="col" class="pr-3.5">Tenor maks (bulan)</th></tr></thead>
                    <tbody>
                        @foreach (\App\Support\PotonganData::PEMBERI_BATAS as $pb)
                            <tr>
                                <td class="pl-3.5"><b>{{ \App\Support\PotonganData::label($pb) }}</b></td>
                                @foreach (['persen', 'sisa_min', 'tenor_maks'] as $k)
                                    <td class="{{ $loop->last ? 'pr-3.5' : '' }}">
                                        <input name="batas[{{ $pb }}][{{ $k }}]" value="{{ old("batas.$pb.$k", $aturan['batas'][$pb][$k]) }}" class="inp w-full max-w-[160px] font-mono {{ $errors->has("batas.$pb.$k") ? 'bad' : '' }}" inputmode="decimal" aria-label="{{ \App\Support\PotonganData::label($pb) }} {{ $k }}" @disabled(! $boleh)>
                                        @if ($errors->has("batas.$pb.$k"))<span class="ferr" role="alert">{{ $errors->first("batas.$pb.$k") }}</span>@endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @if ($boleh)<div class="flex justify-end border-t border-[#DAD8D0] pt-3"><button type="submit" class="btn btn-p btn-lg">Simpan aturan</button></div>
        @else<p class="note">Hanya Admin dan Juru Bayar yang bisa mengubah aturan.</p>@endif
    </form>
@endif
@endsection
