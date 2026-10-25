@php $rk = $hasil['rekonsiliasi']; $t = $hasil['tingkat']; @endphp
<section class="card max-w-[720px] !px-6 !py-[22px]">
    <h2 class="!text-lg">Ringkasan sebelum transfer</h2>
    <div class="my-3.5 flex flex-col text-base">
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Anggota terbaca dari DPP</span><span class="font-mono">{{ count($hasil['personel']) }}</span></div>
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Cocok dengan pembukuan</span><span class="font-mono">{{ $hasil['statistik']['cocok'] }} dari {{ $hasil['statistik']['buku_total'] }}</span></div>
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Gaji ditulis ke pembukuan baru</span><span class="font-mono">{{ rp($rk['update']) }}</span></div>
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Jumlah DPP untuk anggota yang cocok</span><span class="font-mono">{{ rp($rk['dpp']) }}</span></div>
        <div class="flex justify-between gap-3 pt-3 text-lg font-semibold"><span>Rekonsiliasi</span><span class="{{ $rk['cocok'] ? 'ok-t' : 'alert-t' }}">{{ $rk['cocok'] ? 'COCOK' : 'TIDAK COCOK' }}</span></div>
    </div>

    @if ($t['TINGGI'] > 0)
        <p class="mb-4 flex gap-2 text-[15px]"><span class="mt-0.5 flex-none text-alert">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span><span><strong>{{ $t['TINGGI'] }} temuan tinggi</strong> belum diselesaikan. Selesaikan sebelum transfer. <a class="lnk" href="{{ route('gaji.langkah', [$p, 3, 'lihat' => 'temuan', 'tingkat' => 'TINGGI']) }}">Lihat temuan</a></span></p>
    @endif

    <h3 class="mt-2 font-semibold">Unduh hasil</h3>
    <ul class="mt-2 flex flex-col gap-2">
        @foreach ($berkas as $n => $ukuran)
            <li class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ $n }} <span class="muted">· {{ number_format($ukuran / 1024, 0, ',', '.') }} KB</span></span>
                <a class="btn btn-p" href="{{ route('gaji.unduh', [$p, $n]) }}">Unduh</a>
            </li>
        @endforeach
    </ul>
    <p class="muted mt-3.5">Buka pembukuan baru di Excel atau LibreOffice; rumus dihitung ulang saat dibuka, lalu simpan sekali. Kolom potongan dikosongkan untuk diisi juru bayar.</p>
</section>

@if (! empty($hasil['satuan']))
    @php $st = $hasil['satuan']; $sr = $st['ringkas']; @endphp
    <section class="card max-w-[720px]">
        <h2>Potongan satuan ({{ $st['bank'] }})</h2>
        <p class="mt-1.5 text-[15px]">{{ $sr['dihitung'] }} orang dihitung · total potongan Rp {{ rp($sr['total']) }} · MASS DEBET Rp {{ rp($sr['mass_debet']) }} ({{ $sr['mass_debet_baris'] }} baris)</p>
        @if ($sr['tunggakan'])<p class="alert-t mt-2">@include('partials.ikon', ['n' => 'err', 'u' => 16])Tunggakan koperasi Rp {{ rp($sr['tunggakan']) }} untuk {{ $sr['orang_tunggakan'] }} orang (hanya dicatat).</p>@endif
        @if ($sr['minus'])<p class="alert-t mt-2">@include('partials.ikon', ['n' => 'err', 'u' => 16]){{ $sr['minus'] }} orang gajinya masih minus setelah koperasi nol. Ditulis apa adanya.</p>@endif
        <p class="muted mt-2">{{ count($st['peringatan']) }} peringatan · <a class="lnk" href="{{ route('potongan.index') }}">Buka Potongan Satuan</a></p>
    </section>
@endif

<details class="card max-w-[720px]">
    <summary class="cursor-pointer font-semibold">Catatan proses</summary>
    <pre class="mt-3 overflow-x-auto whitespace-pre-wrap font-mono text-[13px]">{{ implode("\n", $hasil['log']) }}</pre>
</details>
