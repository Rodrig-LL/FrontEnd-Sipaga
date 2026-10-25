@extends('layouts.app')
@section('title', 'Slip '.$r['nama'])

@section('konten')
<div class="no-print flex flex-col gap-3.5">
    @include('partials.kembali', ['href' => route('slip.index'), 'label' => 'Kembali ke daftar slip'])
    <header class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="text-2xl font-semibold">{{ $r['nama'] }} <span class="font-normal text-muted">· {{ $r['pangkat'] ?: '—' }} · {{ \App\Support\DataGaji::bulan() }}</span></h1>
        <div class="flex flex-wrap items-center gap-2.5">
            @if ($sebelum)
                <a class="btn" aria-label="Anggota sebelumnya" href="{{ route('slip.show', $sebelum) }}">←</a>
            @else
                <span class="btn off" aria-disabled="true">←</span>
            @endif
            <span class="muted font-mono">{{ $posisi }} / {{ $total }}</span>
            @if ($sesudah)
                <a class="btn" aria-label="Anggota berikutnya" href="{{ route('slip.show', $sesudah) }}">→</a>
            @else
                <span class="btn off" aria-disabled="true">→</span>
            @endif
            <a class="btn" href="{{ route('slip.cetak', ['n' => $r['n']]) }}">Unduh PDF</a>
            <button type="button" class="btn btn-p" x-data x-on:click="window.print()">Cetak slip</button>
        </div>
    </header>
</div>

<div class="flex flex-wrap items-start gap-4 print:block">
    <div class="min-w-0 flex-[999_1_560px]">@include('partials.slip')</div>

    <aside class="no-print flex min-w-0 flex-[1_1_260px] flex-col gap-3">
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Hasil pemeriksaan</h2>
            <p class="font-semibold {{ $r['stat'] === 'verif' ? 'text-alert' : '' }}">{{ $r['stat'] === 'verif' ? 'Perlu dicek' : 'Tanpa temuan' }}</p>
            @foreach ($r['masalah'] as $m)<p class="muted mt-2 leading-normal"><b>Hitungan DPP tidak konsisten.</b> {{ $m }}</p>@endforeach
            @foreach ($r['tm'] as $t)<p class="muted mt-2 leading-normal"><b>{{ $t['kategori'] }}.</b> {{ $t['detail'] }}</p>@endforeach
            @if ($r['stat'] === 'siap')<p class="muted mt-1.5 leading-normal">Hitungan per anggota di DPP konsisten dan tidak ada temuan untuk anggota ini.</p>@endif
        </section>
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Pencocokan dengan pembukuan</h2>
            @if ($r['baris'] !== null)
                <p class="muted leading-normal">Baris {{ $r['baris'] }}, cocok lewat {{ ['NRP' => 'NRP', 'NAMA' => 'nama (NRP di buku berbeda)', 'PDF' => 'PDF (buku disusun dari template kosong)'][$r['cara']] ?? '—' }}.</p>
                <p class="muted mt-1.5 leading-normal">Gaji lama <span class="font-mono">{{ rp($r['gaji_lama']) }}</span> → baru <span class="font-mono">{{ rp($r['bersih']) }}</span></p>
            @else
                <p class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Belum ada di pembukuan</p>
                <p class="muted mt-1.5 leading-normal">Tidak ditulis ke pembukuan. Tambahkan barisnya di berkas pembukuan.</p>
            @endif
        </section>
        <section class="card !px-4 !py-3.5">
            <h2 class="!mb-1.5 !text-sm">Sumber data</h2>
            <p class="muted leading-normal">{{ $r['file'] }}, halaman {{ $r['halaman'] }}, nomor urut {{ $r['no_urut'] }}.{{ $r['scan'] ? ' Dibaca dari hasil scan (OCR): cocokkan nama, NRP, dan pangkat dengan kertas aslinya.' : '' }}</p>
        </section>
    </aside>
</div>
@endsection
