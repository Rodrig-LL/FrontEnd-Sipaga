@extends('layouts.app')
@section('title', $r ? 'Ubah personel' : 'Tambah personel')

@php
    // old() menang atas nilai awal, supaya isian tidak hilang saat ada galat validasi.
    $v = [];
    foreach ($nilai as $k => $x) $v[$k] = old($k, $x);
    $e = fn ($k) => $errors->first($k);
    $teks = [
        ['jabatan', 'Jabatan'], ['ket', 'Keterangan (KET)'], ['seksi', 'Seksi / bagian'],
    ];
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('personel.index'), 'label' => 'Kembali ke Data Personel'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">{{ $r ? $r->nama : 'Data Personel' }}</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $r ? 'Ubah data personel' : 'Tambah personel' }}</h1>
        <p class="mt-1.5 text-[15px] text-body">Setiap perubahan dicatat di riwayat beserta nama pengguna yang mengubahnya.</p>
    </div>
</header>

<form method="post" action="{{ $r ? route('personel.update', $r) : route('personel.store') }}" class="flex max-w-[820px] flex-col gap-4"
      x-data="{ jenis: {{ \Illuminate\Support\Js::from($v['jenis']) }}, status: {{ \Illuminate\Support\Js::from($v['status']) }} }">
    @csrf
    @if ($r) @method('PUT') @endif

    <section class="card">
        <h2>Identitas</h2>
        <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl md:col-span-2">Nama lengkap
                <input name="nama" value="{{ $v['nama'] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e('nama') ? 'bad' : '' }}">
                @if ($e('nama'))<span class="ferr" role="alert">{{ $e('nama') }}</span>@endif
            </label>

            <label class="fl">Jenis personel
                <select name="jenis" class="inp w-full text-base" x-model="jenis">
                    <option value="TNI">TNI</option>
                    <option value="PNS">PNS</option>
                </select>
                @if ($e('jenis'))<span class="ferr" role="alert">{{ $e('jenis') }}</span>@endif
            </label>

            <label class="fl"><span x-text="jenis === 'TNI' ? 'NRP' : 'NIP'">NRP</span>
                <input name="nrp_asli" value="{{ $v['nrp_asli'] }}" type="text" inputmode="numeric" autocomplete="off" class="inp w-full font-mono text-base {{ $e('nrp_asli') ? 'bad' : '' }}">
                @if ($e('nrp_asli'))<span class="ferr" role="alert">{{ $e('nrp_asli') }}</span>@endif
            </label>

            <label class="fl"><span x-text="jenis === 'TNI' ? 'Pangkat dan korps' : 'Golongan'">Pangkat dan korps</span>
                <input name="pangkat" value="{{ $v['pangkat'] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e('pangkat') ? 'bad' : '' }}" x-bind:placeholder="jenis === 'TNI' ? 'mis. SERKA CAJ' : 'mis. III/b'">
                @if ($e('pangkat'))<span class="ferr" role="alert">{{ $e('pangkat') }}</span>@endif
            </label>

            <label class="fl">Tanggal lahir <span class="muted">(PNS)</span>
                <input name="tgl_lahir" value="{{ $v['tgl_lahir'] }}" type="date" class="inp w-full text-base {{ $e('tgl_lahir') ? 'bad' : '' }}">
                @if ($e('tgl_lahir'))<span class="ferr" role="alert">{{ $e('tgl_lahir') }}</span>@endif
            </label>

            @foreach ($teks as [$k, $label])
                <label class="fl {{ $loop->last ? 'md:col-span-2' : '' }}">{{ $label }}
                    <input name="{{ $k }}" value="{{ $v[$k] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e($k) ? 'bad' : '' }}">
                    @if ($e($k))<span class="ferr" role="alert">{{ $e($k) }}</span>@endif
                </label>
            @endforeach
        </div>
    </section>

    <section class="card">
        <h2>Bank dan rekening</h2>
        <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl">Bank
                <select name="bank" class="inp w-full text-base {{ $e('bank') ? 'bad' : '' }}">
                    <option value="">Belum diisi</option>
                    @foreach (\App\Models\Personel::BANK as $b)
                        <option @selected($v['bank'] === $b)>{{ $b }}</option>
                    @endforeach
                </select>
                @if ($e('bank'))<span class="ferr" role="alert">{{ $e('bank') }}</span>@endif
            </label>
            <label class="fl">Nomor rekening
                <input name="rekening" value="{{ $v['rekening'] }}" type="text" inputmode="numeric" autocomplete="off" class="inp w-full font-mono text-base {{ $e('rekening') ? 'bad' : '' }}">
                @if ($e('rekening'))<span class="ferr" role="alert">{{ $e('rekening') }}</span>@endif
            </label>
            <label class="fl md:col-span-2">Nama pemilik rekening
                <input name="nama_rekening" value="{{ $v['nama_rekening'] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e('nama_rekening') ? 'bad' : '' }}">
                @if ($e('nama_rekening'))<span class="ferr" role="alert">{{ $e('nama_rekening') }}</span>@endif
            </label>
        </div>
    </section>

    <section class="card">
        <h2>Status</h2>
        <div class="mt-3 grid gap-x-5 gap-y-4 md:grid-cols-2">
            <label class="fl">Status personel
                <select name="status" class="inp w-full text-base" x-model="status">
                    <option value="aktif">Aktif</option>
                    <option value="keluar">Keluar (pindah, pensiun, dll.)</option>
                </select>
            </label>
            <div class="hidden md:block"></div>
            <label class="fl" x-show="status === 'keluar'" @if ($v['status'] !== 'keluar') style="display: none" @endif>Tanggal keluar
                <input name="tgl_keluar" value="{{ $v['tgl_keluar'] }}" type="date" class="inp w-full text-base {{ $e('tgl_keluar') ? 'bad' : '' }}" x-bind:disabled="status !== 'keluar'">
                @if ($e('tgl_keluar'))<span class="ferr" role="alert">{{ $e('tgl_keluar') }}</span>@endif
            </label>
            <label class="fl" x-show="status === 'keluar'" @if ($v['status'] !== 'keluar') style="display: none" @endif>Alasan keluar
                <input name="alasan_keluar" value="{{ $v['alasan_keluar'] }}" type="text" autocomplete="off" class="inp w-full text-base {{ $e('alasan_keluar') ? 'bad' : '' }}" x-bind:disabled="status !== 'keluar'">
                @if ($e('alasan_keluar'))<span class="ferr" role="alert">{{ $e('alasan_keluar') }}</span>@endif
            </label>
            @if ($r && $r->tandai !== '')
                <label class="chk md:col-span-2"><input type="checkbox" name="hapus_tanda" value="1">Sudah saya cek, hapus tanda “{{ $r->tandai }}”</label>
            @endif
        </div>
        <p class="muted mt-3.5">Personel yang pindah atau pensiun ditandai keluar, tidak dihapus, supaya pencocokan DPP tetap mengenalinya.</p>
    </section>

    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
        <a class="btn btn-o btn-lg !text-[15px] !font-medium" href="{{ route('personel.index') }}">Batal</a>
        <button type="submit" class="btn btn-p btn-lg">Simpan</button>
    </div>
</form>

@if ($r)
    @can('hapus-personel')
        <form method="post" action="{{ route('personel.hapus', $r) }}" class="mt-2 max-w-[820px]" x-data="{ yakin: false }">
            @csrf @method('DELETE')
            <section class="card">
                <h2>Hapus data</h2>
                <p class="muted my-1.5 leading-normal">Hanya untuk data yang salah input. Isi terakhirnya tetap tersimpan di riwayat.</p>
                <button type="button" class="btn" x-show="! yakin" x-on:click="yakin = true">Hapus personel ini…</button>
                <div class="flex flex-wrap items-center gap-2.5" x-show="yakin" x-cloak>
                    <span class="font-semibold">Hapus {{ $r->nama }} secara permanen?</span>
                    <button type="submit" class="btn btn-p !border-alert !bg-alert">Ya, hapus</button>
                    <button type="button" class="btn" x-on:click="yakin = false">Batal</button>
                </div>
            </section>
        </form>
    @endcan

    @if ($riwayat->isNotEmpty())
        <section class="card mt-4 max-w-[820px]">
            <div class="flex items-center justify-between gap-3">
                <h2>Riwayat personel ini</h2>
                <a class="lnk" href="{{ route('personel.riwayat', ['personel' => $r->id]) }}">Lihat semua</a>
            </div>
            <ul class="mt-2 flex flex-col gap-1.5 text-sm">
                @foreach ($riwayat->take(5) as $h)
                    <li><b>{{ $h->aksi }}</b> · {{ $h->waktu->translatedFormat('j M Y H:i') }} · {{ $h->operator }}</li>
                @endforeach
            </ul>
        </section>
    @endif
@endif
@endsection
