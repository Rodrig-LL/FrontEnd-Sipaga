@extends('layouts.app')
@section('title', 'Pratinjau impor')

@php
    $nominatif = $i->jenis === 'nominatif';
    $jenisImpor = $i->jenis;
    $kol = ['jenis' => 'Jenis', 'nama' => 'Nama', 'pangkat' => 'Pangkat/gol.', 'nrp_asli' => 'NRP/NIP', 'jabatan' => 'Jabatan', 'ket' => 'KET', 'seksi' => 'Seksi',
        'tgl_lahir' => 'Tgl lahir', 'bank' => 'Bank', 'rekening' => 'No rekening', 'nama_rekening' => 'Nama rekening', 'tandai' => 'Tanda'];
    $ubah = fn (array $p) => collect($p)->map(fn ($d, $k) => ($kol[$k] ?? $k).': '.(($d[0] ?? '') !== '' ? $d[0].' → ' : '').(($d[1] ?? '') !== '' ? $d[1] : '(kosong)'))->all();
    $maks = 50;
    $diterapkan = $i->status === 'diterapkan';
    $bisa = $bolehImpor && ! $diterapkan && ! $basi && $i->jumlahPerubahan() > 0;
@endphp

@section('konten')
@include('partials.kembali', ['href' => route('personel.impor'), 'label' => 'Kembali ke Impor'])

<header>
    <p class="text-[13px] text-muted">Pratinjau impor {{ strtolower(\App\Models\ImporPersonel::JENIS[$i->jenis]) }} · {{ $i->created_at->translatedFormat('j F Y H:i') }} WIB · {{ $i->user?->name ?? '—' }}</p>
    <h1 class="mt-1 text-[26px] font-semibold tracking-tight">{{ $diterapkan ? 'Impor sudah diterapkan' : 'Periksa sebelum diterapkan' }}</h1>
    <p class="mt-1.5 text-[15px] text-body">{{ implode(' · ', $i->berkas) }}</p>
</header>

@if ($diterapkan)
    <p class="note" role="status">Diterapkan {{ $i->diterapkan_at->translatedFormat('j F Y H:i') }} WIB: {{ $i->hasil['tambah'] ?? 0 }} ditambah, {{ $i->hasil['ubah'] ?? 0 }} diperbarui, {{ $i->hasil['tandai'] ?? 0 }} ditandai, {{ $i->hasil['pinjaman'] ?? 0 }} pinjaman dicatat, {{ $i->hasil['berhenti'] ?? 0 }} berhenti. Setiap perubahan tercatat di <a class="lnk" href="{{ route('personel.riwayat') }}">riwayat</a>.</p>
@elseif ($basi)
    <p class="note" role="alert"><b>Data personel berubah sejak pratinjau ini dibuat.</b> Buat pratinjau ulang dari <a class="lnk" href="{{ route('personel.impor') }}">halaman impor</a> sebelum menerapkan.</p>
@endif
@if ($errors->has('impor'))<p class="note" role="alert">{{ $errors->first('impor') }}</p>@endif

<section class="grid grid-cols-2 gap-3 lg:grid-cols-4" aria-label="Ringkasan pratinjau">
    @if ($nominatif)
        <div class="kpi"><div class="l">Ditambahkan</div><div class="v">{{ count($r['tambah']) }}</div><div class="s">NRP/NIP baru</div></div>
        <div class="kpi"><div class="l">Diperbarui</div><div class="v">{{ count($r['ubah']) }}</div><div class="s">{{ $r['tetap'] }} lainnya tidak berubah</div></div>
        <div class="kpi {{ count($r['hilang']) ? 'border-alert' : '' }}"><div class="l {{ count($r['hilang']) ? 'alert-t' : '' }}">Ditandai perlu dicek</div><div class="v">{{ count($r['hilang']) }}</div><div class="s">tidak tercantum lagi</div></div>
        <div class="kpi {{ count($r['masalah']) ? 'border-alert' : '' }}"><div class="l {{ count($r['masalah']) ? 'alert-t' : '' }}">Bermasalah</div><div class="v">{{ count($r['masalah']) }}</div><div class="s">tidak ikut diimpor</div></div>
    @elseif ($jenisImpor === 'potongan')
        <div class="kpi"><div class="l">Potongan diperbarui</div><div class="v">{{ count($r['ubah']) }}</div><div class="s">{{ $r['tetap'] }} lainnya sudah sama</div></div>
        <div class="kpi {{ count($r['pinjaman_baru']) ? 'border-alert' : '' }}"><div class="l">Pinjaman berjalan</div><div class="v">{{ count($r['pinjaman_baru']) }}</div><div class="s">dicatat, tenor diisi menyusul</div></div>
        <div class="kpi {{ count($r['tidak_ketemu']) ? 'border-alert' : '' }}"><div class="l {{ count($r['tidak_ketemu']) ? 'alert-t' : '' }}">Di buku, tidak di database</div><div class="v">{{ count($r['tidak_ketemu']) }}</div><div class="s">tidak diimpor</div></div>
        <div class="kpi {{ count($r['masalah']) ? 'border-alert' : '' }}"><div class="l {{ count($r['masalah']) ? 'alert-t' : '' }}">Bermasalah</div><div class="v">{{ count($r['masalah']) }}</div><div class="s">tidak diimpor</div></div>
    @elseif ($jenisImpor === 'arisan')
        <div class="kpi"><div class="l">Diisi / diubah</div><div class="v">{{ count($r['ubah']) }}</div><div class="s">{{ $r['tetap'] }} lainnya sudah sama</div></div>
        <div class="kpi {{ count($r['berhenti']) ? 'border-alert' : '' }}"><div class="l {{ count($r['berhenti']) ? 'alert-t' : '' }}">Berhenti arisan</div><div class="v">{{ count($r['berhenti']) }}</div><div class="s">tidak tercantum lagi</div></div>
        <div class="kpi {{ count($r['tidak_ketemu']) ? 'border-alert' : '' }}"><div class="l {{ count($r['tidak_ketemu']) ? 'alert-t' : '' }}">Tidak ketemu</div><div class="v">{{ count($r['tidak_ketemu']) }}</div><div class="s">tidak diisikan ke siapa pun</div></div>
        <div class="kpi {{ count($r['masalah']) ? 'border-alert' : '' }}"><div class="l {{ count($r['masalah']) ? 'alert-t' : '' }}">Bermasalah</div><div class="v">{{ count($r['masalah']) }}</div><div class="s">tercantum ganda</div></div>
    @else
        <div class="kpi"><div class="l">Diisi / diperbarui</div><div class="v">{{ count($r['ubah']) }}</div><div class="s">{{ $r['tetap'] }} lainnya sudah sama</div></div>
        <div class="kpi {{ count($r['tidak_ketemu']) ? 'border-alert' : '' }}"><div class="l {{ count($r['tidak_ketemu']) ? 'alert-t' : '' }}">Di buku, tidak di database</div><div class="v">{{ count($r['tidak_ketemu']) }}</div><div class="s">tidak diisikan ke siapa pun</div></div>
        <div class="kpi {{ count($r['masalah']) ? 'border-alert' : '' }}"><div class="l {{ count($r['masalah']) ? 'alert-t' : '' }}">Bermasalah</div><div class="v">{{ count($r['masalah']) }}</div><div class="s">tidak diisi</div></div>
        <div class="kpi {{ count($r['tetap_tanpa_bank']) ? 'border-alert' : '' }}"><div class="l {{ count($r['tetap_tanpa_bank']) ? 'alert-t' : '' }}">Masih tanpa bank</div><div class="v">{{ count($r['tetap_tanpa_bank']) }}</div><div class="s">sesudah impor ini</div></div>
    @endif
</section>

@foreach ($r['catatan'] as $c)<p class="note">{{ $c }}</p>@endforeach

@php
    // [judul, baris, [[judul kolom, fungsi isi], ...]]
    $orang = fn ($x) => $x['nama'].' · '.($x['nrp'] ?? $x['nrp_asli'] ?? '');
    if ($nominatif) {
        $tabel = [
            ['Bermasalah', $r['masalah'], [['Berkas', fn ($x) => $x['file'].' baris '.$x['baris']], ['Personel', fn ($x) => ($x['nama'] ?: '(tanpa nama)').' · '.$x['nrp']], ['Alasan', fn ($x) => $x['alasan']]]],
            ['Ditandai perlu dicek', $r['hilang'], [['Personel', $orang], ['Pangkat', fn ($x) => $x['pangkat'] ?: '—'], ['Tanda', fn ($x) => $x['tandai']]]],
            ['Diperbarui', $r['ubah'], [['Personel', $orang], ['Perubahan', fn ($x) => implode("\n", $ubah($x['perubahan']))]]],
            ['Ditambahkan', $r['tambah'], [['Personel', $orang], ['Jenis', fn ($x) => $x['jenis']], ['Pangkat / gol.', fn ($x) => $x['pangkat'] ?: '—'],
                ['Jabatan / KET', fn ($x) => trim($x['jabatan'].' '.($x['ket'] && $x['ket'] !== '-' ? '· KET '.$x['ket'] : '')) ?: '—']]],
        ];
    } elseif ($jenisImpor === 'potongan') {
        $pemberi = fn ($x) => \App\Support\PotonganData::label($x['pemberi']);
        $tabel = [
            ['Bermasalah', $r['masalah'], [['Personel', $orang], ['Alasan', fn ($x) => $x['alasan']]]],
            ['Pinjaman berjalan yang akan dicatat', $r['pinjaman_baru'], [['Personel', $orang], ['Pemberi', $pemberi], ['Cicilan', fn ($x) => number_format($x['cicilan'], 0, ',', '.')],
                ['Catatan', fn ($x) => $x['tandai'] ?: 'Tenor diisi menyusul.']]],
            ['Pinjaman tercatat, angkanya beda dengan buku (tidak diubah)', $r['pinjaman_beda'], [['Personel', $orang], ['Pemberi', $pemberi], ['Di buku', fn ($x) => number_format($x['buku'], 0, ',', '.')], ['Di database', fn ($x) => number_format($x['database'], 0, ',', '.')]]],
            ['Di buku, tidak ada di database', $r['tidak_ketemu'], [['Personel', $orang], ['Letak', fn ($x) => $x['file'].' '.$x['sheet'].' baris '.$x['baris']]]],
            ['Potongan diperbarui', $r['ubah'], [['Personel', $orang], ['Cocok lewat', fn ($x) => $x['cara'] === 'nama' ? 'nama' : 'NRP'], ['Perubahan', fn ($x) => implode("\n", $ubah($x['perubahan']))]]],
        ];
    } elseif ($jenisImpor === 'arisan') {
        $tabel = [
            ['Bermasalah', $r['masalah'], [['Personel', $orang], ['Alasan', fn ($x) => $x['alasan']]]],
            ['Tidak ketemu di database', $r['tidak_ketemu'], [['Personel', $orang], ['Letak', fn ($x) => $x['asal']]]],
            ['Berhenti arisan', $r['berhenti'], [['Personel', $orang], ['Bank', fn ($x) => $x['bank']], ['Sekarang', fn ($x) => number_format($x['lama'], 0, ',', '.')]]],
            ['Diisi / diubah', $r['ubah'], [['Personel', $orang], ['Sekarang', fn ($x) => $x['lama'] ? number_format($x['lama'], 0, ',', '.') : '—'], ['Menjadi', fn ($x) => number_format($x['baru'], 0, ',', '.')], ['Dari', fn ($x) => $x['asal']]]],
        ];
    } else {
        $tabel = [
            ['Bermasalah', $r['masalah'], [['Personel', $orang], ['Alasan', fn ($x) => $x['alasan']]]],
            ['Di buku, tidak ada di database', $r['tidak_ketemu'], [['Personel', $orang], ['Bank', fn ($x) => $x['bank']], ['Letak', fn ($x) => $x['file'].' '.$x['sheet'].' baris '.$x['baris']]]],
            ['Masih tanpa bank sesudah impor ini', $r['tetap_tanpa_bank'], [['Personel', $orang], ['Jenis', fn ($x) => $x['jenis']]]],
            ['Diisi / diperbarui', $r['ubah'], [['Personel', $orang], ['Cocok lewat', fn ($x) => $x['cara'] === 'nama' ? 'nama' : 'NRP'],
                ['Perubahan', fn ($x) => implode("\n", $ubah($x['perubahan']))], ['Dari', fn ($x) => $x['asal']]]],
        ];
    }
@endphp

@foreach ($tabel as [$judul, $baris, $kolom])
    @if (count($baris))
        <section class="card overflow-hidden p-0">
            <h2 class="px-3.5 pt-3.5">{{ $judul }} ({{ count($baris) }})</h2>
            <div class="overflow-x-auto">
                <table class="t min-w-[720px]">
                    <thead><tr>@foreach ($kolom as [$h]) <th scope="col" class="{{ $loop->first ? 'pl-3.5' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach (array_slice($baris, 0, $maks) as $x)
                            <tr>@foreach ($kolom as [$h, $fn]) <td class="{{ $loop->first ? 'pl-3.5' : '' }} whitespace-pre-line">{{ $fn($x) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (count($baris) > $maks)<p class="muted px-3.5 py-2.5">… dan {{ count($baris) - $maks }} lainnya.</p>@endif
        </section>
    @endif
@endforeach

@if (! $diterapkan)
    <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-[#DAD8D0] pt-3">
        <span class="muted">{{ $i->jumlahPerubahan() ? 'Database belum berubah. Periksa pratinjau di atas, lalu terapkan.' : 'Tidak ada yang perlu diubah.' }}</span>
        <form method="post" action="{{ route('personel.impor.terapkan', $i) }}">
            @csrf
            <button type="submit" class="btn btn-p btn-lg" @disabled(! $bisa)>Terapkan ke database</button>
        </form>
    </footer>
@endif
@endsection
