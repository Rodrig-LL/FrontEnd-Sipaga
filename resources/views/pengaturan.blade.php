@extends('layouts.app')
@section('title', 'Pengaturan')

@section('konten')
@include('partials.kembali', ['href' => route('dasbor'), 'label' => 'Kembali ke Dasbor'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Pengaturan</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Pengaturan</h1>
        <p class="mt-1.5 text-[15px] text-body">Data ini dipakai pada kop dan tanda tangan slip gaji.</p>
    </div>
</header>

<form method="post" action="{{ route('pengaturan.simpan') }}" class="flex max-w-[820px] flex-col gap-4">
    @csrf
    <section class="card">
        <h2>Identitas pada slip gaji</h2>
        <div class="mt-3.5 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl md:col-span-2">Nama instansi<input name="instansi" value="{{ old('instansi', $s['instansi'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
            <label class="fl md:col-span-2">Alamat instansi<input name="alamat" value="{{ old('alamat', $s['alamat'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
            <label class="fl">Kota penerbitan<input name="kota" value="{{ old('kota', $s['kota'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
            <label class="fl">Nama Juru Bayar<input name="juru" value="{{ old('juru', $s['juru'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
            <label class="fl">Pangkat / NRP Juru Bayar<input name="juru_nrp" value="{{ old('juru_nrp', $s['juru_nrp'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
        </div>
    </section>

    <section class="card">
        <h2>Tampilan</h2>
        <label class="chk mt-2.5"><input type="checkbox" name="lega" value="1" @checked(! empty($s['lega']))>Tabel lega: huruf dan jarak antarbaris lebih besar</label>
    </section>

    @can('ubah-pengaturan')
        <div class="flex justify-end border-t border-[#DAD8D0] pt-3">
            <button type="submit" class="btn btn-p btn-lg">Simpan pengaturan</button>
        </div>
    @else
        <p class="note">Hanya Admin dan Juru Bayar yang bisa mengubah pengaturan ini.</p>
    @endcan
</form>

@endsection
