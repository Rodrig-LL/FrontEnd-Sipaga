<section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" aria-label="Ringkasan pembacaan">
    <div class="kpi"><div class="l">Berkas PDF</div><div class="v">{{ count($hasil['dpp']) }}</div><div class="s">{{ implode(', ', array_unique(array_column($hasil['dpp'], 'bulan'))) ?: '—' }}</div></div>
    <div class="kpi"><div class="l">Anggota terbaca</div><div class="v">{{ count($hasil['personel']) }}</div><div class="s">dari seluruh PDF</div></div>
    <div class="kpi {{ $bermasalah ? 'border-alert' : '' }}"><div class="l {{ $bermasalah ? 'alert-t' : '' }}">@if ($bermasalah)@include('partials.ikon', ['n' => 'err', 'u' => 16])@endif Hitungan tidak konsisten</div><div class="v">{{ $bermasalah }}</div><div class="s">anggota</div></div>
    <div class="kpi"><div class="l">Total dibayarkan</div><div class="v !text-[24px]">{{ rp(array_sum(array_column($hasil['dpp'], 'dibayarkan'))) }}</div><div class="s">menurut DPP</div></div>
</section>

<section class="card overflow-hidden p-0">
    <h2 class="px-3.5 pt-3.5">Hasil baca per PDF</h2>
    <div class="overflow-x-auto">
        <table class="t min-w-[780px]">
            <thead><tr><th scope="col" class="pl-3.5">Berkas</th><th scope="col" class="r">Anggota</th><th scope="col" class="r">Konsisten</th><th scope="col" class="r">Lembar cocok</th><th scope="col" class="r">Gagal dibaca</th><th scope="col" class="r pr-3.5">Dibayarkan</th></tr></thead>
            <tbody>
                @foreach ($hasil['dpp'] as $f)
                    @php $tak = $f['anggota'] - $f['konsisten']; @endphp
                    <tr>
                        <td class="pl-3.5"><b>{{ $f['file'] }}</b>
                            @if ($f['anggota'] === 0 && $f['galat'] === 0)<div class="alert-t sm">@include('partials.ikon', ['n' => 'err', 'u' => 16])Tidak ada anggota terbaca. Mungkin bukan DPP.</div>@endif
                            @if ($f['scan'])<div class="sm">Hasil scan: {{ $f['scan'] }} halaman dibaca dengan OCR. Cocokkan dengan kertas aslinya.</div>@endif
                        </td>
                        <td class="r num">{{ $f['anggota'] }}</td>
                        <td class="r num {{ $tak ? 'alert-t' : '' }}">{{ $f['konsisten'] }}{{ $tak ? ' ('.$tak.' tidak)' : '' }}</td>
                        <td class="r num {{ $f['lembar_cocok'] < $f['lembar'] ? 'alert-t' : '' }}">{{ $f['lembar_cocok'] }} / {{ $f['lembar'] }}</td>
                        <td class="r num {{ $f['galat'] ? 'alert-t' : '' }}">{{ $f['galat'] }}</td>
                        <td class="r num pr-3.5">{{ rp($f['dibayarkan']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

<section class="card overflow-hidden p-0">
    <h2 class="px-3.5 pt-3.5">Anggota terbaca</h2>
    <p class="muted px-3.5 pb-2 pt-1">Cocokkan sekilas dengan berkas asli. Baris bertanda merah hitungannya tidak cocok dengan angka tertulis di DPP.</p>
    <div class="overflow-x-auto">
        <table class="t min-w-[860px]">
            <thead><tr><th scope="col" class="pl-3.5">Nama</th><th scope="col">NRP</th><th scope="col">Pangkat</th><th scope="col" class="r">Gaji pokok</th><th scope="col" class="r">Jumlah kotor</th><th scope="col" class="r pr-3.5">Dibayarkan</th></tr></thead>
            <tbody>
                @forelse ($hal as $o)
                    <tr>
                        <td class="pl-3.5"><b>{{ $o['nama'] }}</b><div class="sm">{{ $o['file'] }} · hal {{ $o['halaman'] }} no. {{ $o['no_urut'] }}</div>
                            @foreach ($o['masalah'] as $m)<div class="alert-t sm">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $m }}</div>@endforeach</td>
                        <td class="num">{{ $o['nrp'] }}</td>
                        <td>{{ trim($o['pangkat'].' '.$o['korps']) ?: '—' }}</td>
                        <td class="r num">{{ rp($o['g_pokok']) }}</td>
                        <td class="r num">{{ rp($o['jml_kotor']) }}</td>
                        <td class="r num pr-3.5"><b>{{ rp($o['dibayarkan']) }}</b></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center muted">Tidak ada anggota yang terbaca.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'anggota'])
</section>
