@extends('layouts.app')
@section('title', 'Potongan '.$p->nama)

@php
    $e = fn ($k) => $errors->first($k);
    $kelompok = ['koperasi' => 'Koperasi', 'bank' => 'Cicilan bank', 'tetap' => 'Iuran dan potongan tetap', 'arisan' => 'Arisan'];
    $perKelompok = [];
    foreach (\App\Support\PotonganData::JENIS as $kode => [$label, $grup]) if (isset($kelompok[$grup])) $perKelompok[$grup][$kode] = $label;
    $pj = $edit;
    $v = fn ($k, $bawaan = '') => old($k, $pj ? ($pj->{$k} ?? $bawaan) : $bawaan);
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('potongan.index'), 'label' => 'Kembali ke Potongan Satuan'])

<header>
    <p class="text-[13px] text-muted">{{ $p->jenis }} · {{ $p->pangkat ?: '—' }} · <span class="font-mono">{{ $p->nrp_asli ?: $p->nrp }}</span></p>
    <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $p->nama }}</h1>
    @if ($p->status === 'keluar')<p class="note mt-2">Personel ini tercatat <b>keluar</b>. Potongannya tidak dihitung di Daftar Potongan.</p>@endif
</header>

<form method="post" action="{{ route('potongan.simpan', $p) }}" class="flex max-w-[900px] flex-col gap-4">
    @csrf
    <section class="card">
        <h2>Potongan tetap per bulan</h2>
        <p class="muted mt-1">Angka dibawa dari bulan ke bulan sampai diubah di sini. Kosong atau 0 = tidak dipotong. Cicilan Koperasi Ditajenad dan LURJA dicatat sebagai pinjaman di bawah.</p>
        @if ($errors->has('_'))<p class="ferr" role="alert">{{ $errors->first('_') }}</p>@endif
        @foreach ($perKelompok as $grup => $daftar)
            <h3 class="mb-2 mt-4 text-[13px] font-semibold tracking-[.06em] text-body">{{ strtoupper($kelompok[$grup]) }}</h3>
            <div class="grid gap-x-5 gap-y-3 md:grid-cols-3">
                @foreach ($daftar as $kode => $label)
                    <label class="fl">{{ $label }}
                        <input name="potongan[{{ $kode }}]" value="{{ old("potongan.$kode", $nilai[$kode] ?? '') }}" class="inp w-full font-mono {{ $e($kode) ? 'bad' : '' }}" inputmode="numeric" autocomplete="off" @disabled(! $boleh)>
                        @if ($e($kode))<span class="ferr" role="alert">{{ $e($kode) }}</span>@endif
                    </label>
                @endforeach
            </div>
        @endforeach
        @if ($boleh)<div class="mt-4 flex justify-end"><button type="submit" class="btn btn-p btn-lg">Simpan potongan</button></div>@endif
    </section>
</form>

<section class="card max-w-[900px]">
    <h2>Pinjaman Koperasi Ditajenad dan LURJA</h2>
    <p class="muted mt-1">Dicatat saat cair (cicilan dan tenor), dipotong sampai lunas. Periode acuan: {{ \App\Support\PotonganData::teksPeriode($periode) }}.</p>
    @if ($pinjaman->isNotEmpty())
        <div class="mt-3 overflow-x-auto">
            <table class="t min-w-[640px]">
                <thead><tr><th scope="col">Pemberi</th><th scope="col" class="r">Cicilan</th><th scope="col">Mulai</th><th scope="col" class="r">Tenor</th><th scope="col">Keadaan</th><th scope="col" class="r">Aksi</th></tr></thead>
                <tbody>
                    @foreach ($pinjaman as $x)
                        @php $j = \App\Support\PotonganData::jalanPada($x, $periode); @endphp
                        <tr>
                            <td>{{ \App\Support\PotonganData::label($x->pemberi) }}{!! $x->catatan ? '<div class="sm">'.e($x->catatan).'</div>' : '' !!}</td>
                            <td class="r num">{{ rp($x->cicilan) }}</td>
                            <td>{{ \App\Support\PotonganData::teksPeriode($x->mulai) }}</td>
                            <td class="r num">@if ($x->tenor === null && $x->status === 'aktif')<span class="alert-t">belum diisi</span>@else{{ $x->tenor ? $x->tenor.' bln' : '—' }}@endif</td>
                            <td>{{ $j['ket'] }}</td>
                            <td class="r act"><a class="lnk" href="{{ route('potongan.orang', ['personel' => $p, 'pinjaman' => $x->id]) }}#form-pinjaman">{{ $boleh ? 'Ubah' : 'Lihat' }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($boleh)
        <form id="form-pinjaman" method="post" action="{{ $pj ? route('potongan.pinjaman.ubah', $pj) : route('potongan.pinjaman.tambah') }}" class="mt-4 border-t border-[#DAD8D0] pt-4">
            @csrf
            @if ($pj) @method('PUT') @else <input type="hidden" name="personel_id" value="{{ $p->id }}"> @endif
            <h3 class="font-semibold">{{ $pj ? 'Ubah pinjaman' : 'Catat pinjaman baru' }}</h3>
            @if ($e('_'))<p class="ferr" role="alert">{{ $e('_') }}</p>@endif
            <div class="mt-3 grid gap-x-5 gap-y-3 md:grid-cols-3">
                <label class="fl">Pemberi
                    <select name="pemberi" class="inp w-full {{ $e('pemberi') ? 'bad' : '' }}">
                        @foreach (\App\Support\PotonganData::PINJAMAN as $k => $l)<option value="{{ $k }}" @selected($v('pemberi', 'KOPERASI') === $k)>{{ $l }}</option>@endforeach
                    </select>
                    @if ($e('pemberi'))<span class="ferr" role="alert">{{ $e('pemberi') }}</span>@endif
                </label>
                <label class="fl">Cicilan per bulan, Rp
                    <input name="cicilan" value="{{ $v('cicilan') }}" class="inp w-full font-mono {{ $e('cicilan') ? 'bad' : '' }}" inputmode="numeric" autocomplete="off">
                    @if ($e('cicilan'))<span class="ferr" role="alert">{{ $e('cicilan') }}</span>@endif
                </label>
                <label class="fl">Potongan pertama (bulan)
                    <input type="month" name="mulai" value="{{ $v('mulai', $periode) }}" class="inp w-full {{ $e('mulai') ? 'bad' : '' }}">
                    @if ($e('mulai'))<span class="ferr" role="alert">{{ $e('mulai') }}</span>@endif
                </label>
                <label class="fl">Tenor (bulan)
                    <input name="tenor" value="{{ $v('tenor') }}" class="inp w-full font-mono {{ $e('tenor') ? 'bad' : '' }}" inputmode="numeric" autocomplete="off">
                    @if ($e('tenor'))<span class="ferr" role="alert">{{ $e('tenor') }}</span>@endif
                </label>
                <label class="fl">Tanggal cair
                    <input type="date" name="tgl_cair" value="{{ $v('tgl_cair') }}" class="inp w-full {{ $e('tgl_cair') ? 'bad' : '' }}">
                    @if ($e('tgl_cair'))<span class="ferr" role="alert">{{ $e('tgl_cair') }}</span>@endif
                </label>
                <label class="fl">Status
                    <select name="status" class="inp w-full">
                        @foreach (['aktif' => 'Aktif', 'lunas' => 'Lunas', 'batal' => 'Batal'] as $k => $l)<option value="{{ $k }}" @selected($v('status', 'aktif') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </label>
                <label class="fl md:col-span-3">Catatan
                    <input name="catatan" value="{{ $v('catatan') }}" class="inp w-full" autocomplete="off">
                </label>
            </div>
            <div class="mt-3 flex flex-wrap justify-end gap-2">
                @if ($pj)<a class="btn" href="{{ route('potongan.orang', $p) }}">Batal</a>@endif
                <button type="submit" class="btn btn-p">{{ $pj ? 'Simpan perubahan' : 'Catat pinjaman' }}</button>
            </div>
        </form>
    @endif
</section>

<section class="card max-w-[900px]">
    <h2>Simpanan pokok koperasi</h2>
    <p class="muted mt-1">Untuk anggota koperasi baru: dipotong sekali di bulan yang dipilih, masuk ke kolom simpanan wajib. Kosongkan bulan untuk membatalkan.</p>
    @if ($boleh)
        <form method="post" action="{{ route('potongan.pokok', $p) }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            <label class="fl">Bulan<input type="month" name="bulan" value="{{ old('bulan', $pokok->bulan ?? '') }}" class="inp {{ $e('bulan') ? 'bad' : '' }}">
                @if ($e('bulan'))<span class="ferr" role="alert">{{ $e('bulan') }}</span>@endif</label>
            <label class="fl">Nominal, Rp<input name="nominal" value="{{ old('nominal', $pokok->nominal ?? ($aturan['pokok'] ?? '')) }}" class="inp font-mono {{ $e('nominal') ? 'bad' : '' }}" inputmode="numeric" autocomplete="off">
                @if ($e('nominal'))<span class="ferr" role="alert">{{ $e('nominal') }}</span>@endif</label>
            <button type="submit" class="btn btn-p h-11">Simpan</button>
        </form>
    @elseif ($pokok)
        <p class="mt-2">{{ rp($pokok->nominal) }} di {{ \App\Support\PotonganData::teksPeriode($pokok->bulan) }}</p>
    @else
        <p class="muted mt-2">Tidak ada.</p>
    @endif
</section>
@endsection
