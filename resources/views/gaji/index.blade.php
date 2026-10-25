@extends('layouts.app')
@section('title', 'Proses Gaji · Langkah 1')

@php
    $label = ['Unggah berkas', 'Periksa hasil baca', 'Cocokkan data', 'Verifikasi selisih', 'Unduh hasil'];
    $statusLabel = ['menunggu' => 'Menunggu', 'berjalan' => 'Berjalan', 'selesai' => 'Selesai', 'gagal' => 'Gagal'];
    // Langkah 2-5 membuka hasil proses yang sedang dipilih (periode aktif); belum ada = belum bisa dibuka.
    $tautan = [1 => route('gaji.index')] + collect(range(2, 5))->mapWithKeys(fn ($n) => [$n => $aktif ? route('gaji.langkah', [$aktif, $n]) : null])->all();
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('dashboard'), 'label' => 'Kembali ke Dashboard'])

<header class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-[13px] text-muted">Proses gaji · mulai proses baru dari PDF DPP dan pembukuan bulan lalu</p>
        <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Langkah 1 dari 5: Unggah berkas</h1>
        <p class="mt-1.5 text-[15px] text-body">Unggah PDF DPP bulan ini dan pembukuan bulan lalu. Hasilnya: pembukuan baru, laporan validasi, dan Daftar Potongan.</p>
    </div>
</header>

@include('gaji._tahap', ['langkah' => 1])

@unless ($pythonAda)
    <p class="note" role="alert"><b>Python belum terpasang untuk aplikasi ini.</b> Jalankan:
        <code>python3 -m venv python/.venv &amp;&amp; python/.venv/bin/pip install -r python/requirements.txt</code></p>
@endunless
@if ($errors->has('hapus'))
    <p class="note" role="alert"><span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span> {{ $errors->first('hapus') }}</p>
@endif

@if ($bolehJalan)
    <section class="card max-w-[820px]">
        <h2>Proses baru</h2>
        <form method="post" action="{{ route('gaji.store') }}" enctype="multipart/form-data" class="mt-3 flex flex-col gap-4">
            @csrf
            <label class="fl">PDF DPP bulan ini <span class="muted">(satu atau lebih, dari satu bank)</span>
                <input type="file" name="pdf[]" multiple accept=".pdf,application/pdf" class="inp h-auto py-2 text-sm {{ $errors->has('pdf') || $errors->has('pdf.*') ? 'bad' : '' }}">
                @if ($errors->has('pdf'))<span class="ferr" role="alert">{{ $errors->first('pdf') }}</span>@endif
                @foreach ($errors->get('pdf.*') as $m)<span class="ferr" role="alert">{{ $m[0] }}</span>@endforeach
            </label>
            <label class="fl">Pembukuan bulan lalu <span class="muted">(.xlsx; nama bank, mis. BRI, dibaca dari nama berkas)</span>
                <input type="file" name="buku" accept=".xlsx" class="inp h-auto py-2 text-sm {{ $errors->has('buku') ? 'bad' : '' }}">
                @if ($errors->has('buku'))<span class="ferr" role="alert">{{ $errors->first('buku') }}</span>@endif
            </label>
            <label class="fl max-w-[280px]">Bulan <span class="muted">(opsional)</span>
                <input name="bulan" value="{{ old('bulan') }}" placeholder="NOVEMBER 2026" class="inp {{ $errors->has('bulan') ? 'bad' : '' }}" autocomplete="off">
                @if ($errors->has('bulan'))<span class="ferr" role="alert">{{ $errors->first('bulan') }}</span>@endif
            </label>
            <p class="muted">Dicocokkan dengan {{ $tniAktif }} personel TNI aktif di Data Personel. Berkas yang diunggah disimpan di server ini saja.</p>
            <div><button type="submit" class="btn btn-p btn-lg" @disabled(! $pythonAda)>Proses berkas</button></div>
        </form>
    </section>
@else
    <p class="note">Hanya Juru Bayar yang bisa menjalankan proses gaji. Anda tetap bisa melihat hasilnya di bawah.</p>
@endif

<section class="card overflow-hidden p-0">
    <h2 class="px-3.5 pt-3.5">Proses sebelumnya</h2>
    <div class="overflow-x-auto">
        <table class="t min-w-[680px]">
            <thead><tr><th scope="col" class="pl-3.5">Waktu</th><th scope="col">Bulan</th><th scope="col">Oleh</th><th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th></tr></thead>
            @forelse ($daftar as $d)
                {{-- Satu <tbody> per proses: baris data, lalu baris konfirmasi hapus (penuh selebar tabel) yang muncul saat "Hapus" diklik. --}}
                <tbody x-data="{ yakin: false }">
                    <tr>
                        <td class="whitespace-nowrap pl-3.5">{{ $d->created_at->translatedFormat('j M Y H:i') }}
                            @if ($aktif && $d->id === $aktif->id)<div class="sm">dipakai Dashboard, Slip, dan Rekap</div>@endif</td>
                        <td>{{ $d->bulan ? mb_convert_case($d->bulan, MB_CASE_TITLE) : '—' }}</td>
                        <td>{{ $d->user?->name ?? '—' }}</td>
                        <td>@if ($d->status === 'gagal')<span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $statusLabel[$d->status] }}</span>@else{{ $statusLabel[$d->status] ?? $d->status }}@endif</td>
                        <td class="r act whitespace-nowrap pr-3.5">
                            <a class="lnk" href="{{ route('gaji.show', $d) }}">Buka</a>
                            @can('hapus-proses')
                                @if ($d->bisaDihapus())
                                    <button type="button" class="btn-link ml-3 !text-alert" x-show="! yakin" x-on:click="yakin = true" aria-label="Hapus proses {{ $d->created_at->translatedFormat('j M Y H:i') }}">Hapus</button>
                                @else
                                    <span class="muted ml-3 text-sm">masih berjalan</span>
                                @endif
                            @endcan
                        </td>
                    </tr>
                    @can('hapus-proses')
                        @if ($d->bisaDihapus())
                            <tr x-show="yakin" x-cloak>
                                <td colspan="5" class="!bg-[#FBF1EF] pl-3.5">
                                    <form method="post" action="{{ route('gaji.hapus', $d) }}" class="flex flex-wrap items-center justify-between gap-3">
                                        @csrf @method('DELETE')
                                        <span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Hapus proses {{ $d->created_at->translatedFormat('j M Y H:i') }} beserta berkas hasilnya?</span>
                                        <span class="flex gap-2">
                                            <button type="button" class="btn !h-9" x-on:click="yakin = false">Batal</button>
                                            <button type="submit" class="btn btn-p !h-9 !border-alert !bg-alert">Ya, hapus</button>
                                        </span>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @endcan
                </tbody>
            @empty
                <tbody><tr><td colspan="5" class="p-6 text-center muted">Belum ada proses.</td></tr></tbody>
            @endforelse
        </table>
    </div>
    @can('hapus-proses')
        @if ($daftar->isNotEmpty())
            <p class="muted px-3.5 py-3">Menghapus proses ikut menghapus berkas hasilnya (pembukuan baru, laporan validasi, Daftar Potongan) dan slip, dashboard, serta rekap periode itu. <b>Unduh dulu</b> berkas yang masih dibutuhkan. Penghapusan tercatat di <a class="lnk" href="{{ route('personel.riwayat') }}">riwayat</a>.</p>
        @endif
    @endcan
</section>

<footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
    <span class="btn btn-o btn-lg off !text-[15px] !font-medium" aria-disabled="true">← Langkah sebelumnya</span>
    <div class="flex flex-wrap items-center gap-3">
        @if ($aktif)
            <span class="muted">Hasil proses terakhir: {{ $aktif->bulan ? mb_convert_case($aktif->bulan, MB_CASE_TITLE) : 'bulan tidak terbaca' }}</span>
            <a class="btn btn-p btn-lg" href="{{ $tautan[2] }}">Lanjut: {{ $label[1] }} →</a>
        @else
            <span class="muted">Belum ada hasil proses untuk dibuka</span>
            <span class="btn btn-p btn-lg off" aria-disabled="true">Lanjut: {{ $label[1] }} →</span>
        @endif
    </div>
</footer>
@endsection
