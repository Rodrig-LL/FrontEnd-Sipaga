@php
    $s = $hasil['statistik'];
    $netral = ['COCOK', 'BUKAN_BANK_INI'];
    $nama = ['KURANG' => 'Kurang (tidak ada di DPP)', 'CEK_BANK' => 'Tidak ada di DPP, cek bank', 'KELUAR_DIBAYAR' => 'Keluar tapi masih dibayar',
        'LEBIH' => 'Ada di DPP, tidak ada di database', 'NRP_BEDA' => 'NRP berbeda', 'NAMA_BEDA' => 'Nama berbeda',
        'REKENING_BEDA' => 'Rekening berbeda', 'BUKAN_BANK_INI' => 'Bank lain (netral)', 'COCOK' => 'Cocok'];
    $tampil = fn ($st) => ! in_array($st, $netral, true);
@endphp

<section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Pencocokan dengan pembukuan">
    <div class="kpi"><div class="l">Cocok dengan pembukuan</div><div class="v">{{ $s['cocok'] }}</div><div class="s">dari {{ $s['buku_total'] }} baris pembukuan</div></div>
    <div class="kpi {{ $s['tidak_di_dpp'] ? 'border-alert' : '' }}"><div class="l {{ $s['tidak_di_dpp'] ? 'alert-t' : '' }}">Di pembukuan, tidak di DPP</div><div class="v">{{ $s['tidak_di_dpp'] }}</div><div class="s">{{ rp($s['nilai_tidak_di_dpp']) }} gaji lama</div></div>
    <div class="kpi {{ $s['dpp_tanpa_buku'] ? 'border-alert' : '' }}"><div class="l {{ $s['dpp_tanpa_buku'] ? 'alert-t' : '' }}">Di DPP, belum di pembukuan</div><div class="v">{{ $s['dpp_tanpa_buku'] }}</div><div class="s">{{ rp($s['nilai_dpp_tanpa_buku']) }} menurut DPP</div></div>
    <div class="kpi {{ $hasil['tingkat']['TINGGI'] ? 'border-alert' : '' }}"><div class="l {{ $hasil['tingkat']['TINGGI'] ? 'alert-t' : '' }}">Temuan tinggi</div><div class="v">{{ $hasil['tingkat']['TINGGI'] }}</div><div class="s">{{ $hasil['tingkat']['SEDANG'] }} sedang · {{ $hasil['tingkat']['INFO'] }} info</div></div>
</section>

@if ($hasil['kosong'])
    <p class="note">Pembukuan yang diunggah berupa <b>template kosong</b>: tabelnya diisi dari PDF, jadi "tidak ada di DPP" tidak bisa dihitung dari pembukuan.</p>
@endif

<div class="fl" role="group" aria-label="Tampilan">
    <div class="seg w-fit">
        <a href="{{ route('gaji.langkah', [$p, 3, 'lihat' => 'personel']) }}" aria-current="{{ $lihat === 'personel' ? 'true' : 'false' }}">Pencocokan Data Personel</a>
        <a href="{{ route('gaji.langkah', [$p, 3, 'lihat' => 'temuan']) }}" aria-current="{{ $lihat === 'temuan' ? 'true' : 'false' }}">Temuan ({{ count($hasil['temuan']) }})</a>
    </div>
</div>

@if ($lihat === 'personel')
    @if ($hasil['pencocokan'])
        @php $pc = $hasil['pencocokan']; @endphp
        <section class="card">
            <h2>Pencocokan dengan Data Personel</h2>
            <p class="muted mt-1">{{ $pc['aktif'] }} personel {{ $pc['jenis'] }} aktif, {{ $pc['keluar'] }} keluar · bank DPP: {{ $pc['bank'] ?: 'tidak diketahui' }}{{ ! empty($pc['bank_dari']) ? ' (dari '.$pc['bank_dari'].')' : '' }}</p>
            <ul class="mt-2 grid gap-1.5 sm:grid-cols-3">
                @foreach ($pc['hitung'] as $k => $n)
                    <li class="flex justify-between gap-3 {{ $n && $tampil($k) ? 'alert-t' : '' }}"><span>{{ $nama[$k] ?? $k }}</span><b class="num">{{ $n }}</b></li>
                @endforeach
            </ul>
        </section>

        <section class="card overflow-hidden p-0">
            <div class="flex flex-wrap items-end justify-between gap-3 px-3.5 pt-3.5">
                <h2>Daftar pencocokan</h2>
                <form method="get" class="flex items-end gap-2">
                    <input type="hidden" name="lihat" value="personel">
                    <label class="fl">Tampilkan
                        <select name="status" class="inp min-w-[220px]" x-data x-on:change="$el.form.submit()">
                            <option value="perlu" @selected($status === 'perlu')>Perlu diperiksa</option>
                            <option value="semua" @selected($status === 'semua')>Semua</option>
                            @foreach ($nama as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </label>
                    <noscript><button class="btn btn-p">Terapkan</button></noscript>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="t min-w-[860px]">
                    <thead><tr><th scope="col" class="pl-3.5">Status</th><th scope="col">Data Personel</th><th scope="col">Menurut DPP</th><th scope="col" class="pr-3.5">Keterangan</th></tr></thead>
                    <tbody>
                        @forelse ($hal as $b)
                            <tr>
                                <td class="pl-3.5 whitespace-nowrap">@if ($tampil($b['status']))<span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $nama[$b['status']] ?? $b['status'] }}</span>@else{{ $nama[$b['status']] ?? $b['status'] }}@endif</td>
                                <td>{{ $b['nama'] ?: '—' }}<div class="sm num">{{ trim($b['pangkat'].' '.$b['nrp']) }}</div></td>
                                <td>{{ $b['nama_dpp'] ?: '—' }}<div class="sm num">{{ trim($b['pangkat_dpp'].' '.$b['nrp_dpp']) }}</div>@if ($b['sumber'])<div class="sm">{{ $b['sumber'] }}</div>@endif</td>
                                <td class="pr-3.5">{{ $b['detail'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-6 text-center muted">Tidak ada data untuk pilihan ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @include('partials.pager', ['label' => 'baris'])
        </section>
    @else
        <p class="note">Proses ini tidak dicocokkan dengan Data Personel.</p>
    @endif
@else
    <section class="card overflow-hidden p-0">
        <div class="flex flex-wrap items-end justify-between gap-3 px-3.5 pt-3.5">
            <h2>Temuan</h2>
            <form method="get" class="flex items-end gap-2">
                <input type="hidden" name="lihat" value="temuan">
                <label class="fl">Tingkat
                    <select name="tingkat" class="inp min-w-[160px]" x-data x-on:change="$el.form.submit()">
                        <option value="">Semua</option>
                        @foreach (['TINGGI' => 'Tinggi', 'SEDANG' => 'Sedang', 'INFO' => 'Info'] as $k => $v)<option value="{{ $k }}" @selected($tingkat === $k)>{{ $v }}</option>@endforeach
                    </select>
                </label>
                <noscript><button class="btn btn-p">Terapkan</button></noscript>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="t min-w-[760px]">
                <thead><tr><th scope="col" class="pl-3.5">Tingkat</th><th scope="col">Kategori</th><th scope="col">Personel</th><th scope="col" class="pr-3.5">Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($hal as $t)
                        <tr>
                            <td class="pl-3.5 whitespace-nowrap">@if ($t['tingkat'] === 'TINGGI')<span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Tinggi</span>@else{{ ucfirst(strtolower($t['tingkat'])) }}@endif</td>
                            <td>{{ $t['kategori'] }}</td>
                            <td>{{ $t['nama'] ?: '—' }}<div class="sm num">{{ $t['nrp'] }}</div></td>
                            <td class="pr-3.5">{{ $t['detail'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-6 text-center muted">Tidak ada temuan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('partials.pager', ['label' => 'temuan'])
    </section>
@endif
