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
    <span class="badge">Data contoh</span>
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
            <label class="fl">Nama operator (tampil di menu samping)<input name="operator" value="{{ old('operator', $s['operator'] ?? '') }}" class="inp w-full text-base" type="text" autocomplete="off"></label>
        </div>
    </section>

    <section class="card">
        <h2>Tampilan</h2>
        <label class="chk mt-2.5"><input type="checkbox" name="lega" value="1" @checked(! empty($s['lega']))>Tabel lega: huruf dan jarak antarbaris lebih besar</label>
    </section>

    <div class="flex justify-end border-t border-[#DAD8D0] pt-3">
        <button type="submit" class="btn btn-p btn-lg">Simpan pengaturan</button>
    </div>
</form>

<section class="card max-w-[820px]" x-data="{ yakin: false }">
    <h2>Data uji coba</h2>
    <p class="muted mb-3 mt-1.5 leading-normal">Menghapus semua yang tersimpan di sesi ini: kemajuan proses gaji, personel yang ditambah atau diubah, dan pengaturan.</p>
    <button type="button" class="btn" x-show="!yakin" x-on:click="yakin = true">Kembalikan data uji coba ke awal</button>
    <form method="post" action="{{ route('pengaturan.reset') }}" x-show="yakin" x-cloak>
        @csrf
        <p class="alert-t mb-2.5">@include('partials.ikon', ['n' => 'err', 'u' => 16])Semua data uji coba akan dihapus. Lanjutkan?</p>
        <div class="flex gap-2">
            <button type="submit" class="btn btn-p">Ya, hapus semua</button>
            <button type="button" class="btn" x-on:click="yakin = false">Batal</button>
        </div>
    </form>
</section>
@endsection
