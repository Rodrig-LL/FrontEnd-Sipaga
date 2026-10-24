@extends('layouts.app')
@section('title', $r ? 'Ubah personel' : 'Tambah personel')

@php
    // old() menang atas nilai awal, supaya isian tidak hilang saat ada galat validasi.
    $v = [];
    foreach ($nilai as $k => $x) $v[$k] = old($k, $x);
    $judul = ! $r ? 'Tambah personel' : ($r['kurang'] ? 'Lengkapi data personel' : 'Ubah data personel');
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('personel.index'), 'label' => 'Kembali ke Data Personel'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $r ? $r['nama'] : 'Data Personel' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $judul }}</h1>
        <p class="mt-1.5 text-[15px] text-body">Isi semua kolom, lalu klik Simpan.</p>
    </div>
    <span class="badge">Data contoh</span>
</header>

<form method="post" action="{{ $r ? route('personel.update', $r['n']) : route('personel.store') }}" class="flex max-w-[820px] flex-col gap-4"
      x-data="{ jenis: {{ \Illuminate\Support\Js::from($v['jenis']) }}, kawin: {{ \Illuminate\Support\Js::from($v['kawin']) }} }">
    @csrf
    @if ($r) @method('PUT') @endif

    <section class="card">
        <div class="mt-1 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl md:col-span-2">Nama lengkap
                <input name="nama" value="{{ $v['nama'] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $errors->first('nama') ? 'bad' : '' }}">
                @if ($errors->first('nama'))<span class="ferr" role="alert">{{ $errors->first('nama') }}</span>@endif
            </label>

            <label class="fl">Jenis personel
                <select name="jenis" class="inp w-full text-base" x-model="jenis" x-on:change="$root.querySelectorAll('select[name=pangkat]').forEach(s => s.selectedIndex = 0)">
                    <option value="TNI">TNI</option>
                    <option value="PNS">PNS</option>
                </select>
            </label>

            <label class="fl"><span x-text="jenis === 'TNI' ? 'NRP' : 'NIP'">NRP</span>
                <input name="nrp" value="{{ $v['nrp'] }}" type="text" autocomplete="off" class="inp w-full font-mono text-base {{ $errors->first('nrp') ? 'bad' : '' }}">
                @if ($errors->first('nrp'))<span class="ferr" role="alert">{{ $errors->first('nrp') }}</span>@endif
            </label>

            {{-- Dua daftar pangkat; hanya yang sesuai jenis yang tampil & ikut terkirim. --}}
            @foreach (['TNI' => 'Pangkat', 'PNS' => 'Pangkat / Golongan'] as $jn => $labelPangkat)
                <label class="fl" x-show="jenis === '{{ $jn }}'" @if ($v['jenis'] !== $jn) style="display: none" @endif>{{ $labelPangkat }}
                    <select name="pangkat" class="inp w-full text-base {{ $errors->first('pangkat') && $v['jenis'] === $jn ? 'bad' : '' }}" x-bind:disabled="jenis !== '{{ $jn }}'">
                        <option value="">Pilih…</option>
                        @foreach (\App\Support\Demo::pangkat($jn) as $p)
                            <option @selected($v['jenis'] === $jn && $v['pangkat'] === $p)>{{ $p }}</option>
                        @endforeach
                    </select>
                    @if ($errors->first('pangkat') && $v['jenis'] === $jn)<span class="ferr" role="alert">{{ $errors->first('pangkat') }}</span>@endif
                </label>
            @endforeach

            <label class="fl">Bagian
                <select name="bagian" class="inp w-full text-base {{ $errors->first('bagian') ? 'bad' : '' }}">
                    <option value="">Pilih…</option>
                    @foreach (\App\Support\Demo::BAGIAN as $b)
                        <option @selected($v['bagian'] === $b)>{{ $b }}</option>
                    @endforeach
                </select>
                @if ($errors->first('bagian'))<span class="ferr" role="alert">{{ $errors->first('bagian') }}</span>@endif
            </label>

            <label class="fl">Status keluarga
                <select name="kawin" class="inp w-full text-base" x-model="kawin">
                    <option value="0">Belum kawin</option>
                    <option value="1">Kawin</option>
                </select>
            </label>

            <label class="fl">Jumlah anak
                <select name="anak" class="inp w-full text-base" x-bind:disabled="kawin !== '1'">
                    @foreach ([0, 1, 2, 3, 4, 5] as $i)
                        <option @selected((string) $i === (string) $v['anak'])>{{ $i }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        <p class="muted mt-3.5">Komponen gaji dihitung otomatis dari pangkat dan status keluarga.</p>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
        <a class="btn btn-o btn-lg !text-[15px] !font-medium" href="{{ route('personel.index') }}">Batal</a>
        <button type="submit" class="btn btn-p btn-lg">Simpan</button>
    </div>
</form>
@endsection
