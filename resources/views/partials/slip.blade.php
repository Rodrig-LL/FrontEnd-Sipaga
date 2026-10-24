{{-- Dokumen slip gaji menurut DPP. Wajib: $r. Opsional: $pb (beri jeda halaman saat dicetak) --}}
@php
    $set = \App\Models\Pengaturan::semua();
    $pg = fn ($k, $bawaan) => ($set[$k] ?? '') !== '' ? $set[$k] : $bawaan;
@endphp
<article class="slip {{ ! empty($pb) ? 'pb mb-4' : '' }}" aria-label="Slip gaji {{ $r['nama'] }}">
    <div class="flex items-center gap-4 border-b-2 border-ink pb-3.5">
        @include('partials.logo', ['ukuran' => 'h-14 w-[4.5rem]'])
        <div class="min-w-0 flex-1">
            <div class="text-[15px] font-semibold tracking-[.04em]">{{ $pg('instansi', '[NAMA INSTANSI]') }}</div>
            <div class="text-xs text-muted">{{ $pg('alamat', '[Alamat instansi]') }}</div>
        </div>
        <div class="text-right">
            <div class="text-base font-semibold tracking-[.08em]">SLIP GAJI</div>
            <div class="text-xs text-muted">Bulan {{ \App\Support\DataGaji::bulan() }}</div>
        </div>
    </div>

    <dl class="slip-meta">
        <div><dt>Nama</dt><dd><b>{{ $r['nama'] }}</b></dd></div>
        <div><dt>NRP</dt><dd class="font-mono">{{ $r['nrp'] ?: '—' }}</dd></div>
        <div><dt>Pangkat</dt><dd>{{ $r['pangkat'] ?: '—' }}</dd></div>
        <div><dt>Status kawin</dt><dd>{{ $r['kawin'] ?: '—' }}</dd></div>
        <div><dt>Jabatan</dt><dd>{{ $r['jabatan'] ?: '—' }}</dd></div>
    </dl>

    <div class="slip-cols grid grid-cols-2 gap-6">
        <section>
            <h2>PENGHASILAN</h2>
            <table><tbody>
                @foreach ($r['earn'] as [$label, $nilai])
                    <tr><td>{{ $label }}</td><td>{{ rp($nilai) }}</td></tr>
                @endforeach
                <tr class="tot"><td>Jumlah penghasilan</td><td>{{ rp($r['kotor']) }}</td></tr>
            </tbody></table>
        </section>
        <section>
            <h2>POTONGAN</h2>
            <table><tbody>
                @forelse ($r['ded'] as [$label, $nilai])
                    <tr><td>{{ $label }}</td><td>{{ rp($nilai) }}</td></tr>
                @empty
                    <tr><td>Tidak ada</td><td>—</td></tr>
                @endforelse
                <tr class="tot"><td>Jumlah potongan</td><td>{{ rp($r['pot']) }}</td></tr>
            </tbody></table>
        </section>
    </div>

    <div class="mt-5 flex flex-wrap items-baseline justify-between gap-2 rounded bg-paper px-4 py-3.5">
        <div>
            <div class="text-xs font-semibold tracking-[.08em] text-muted">JUMLAH DIBAYARKAN</div>
            <div class="mt-0.5 text-xs italic capitalize text-muted">{{ terbilang($r['bersih']) }} rupiah</div>
        </div>
        <div class="font-mono text-[22px] font-medium">Rp {{ rp($r['bersih']) }}</div>
    </div>

    @if ($r['masalah'])
        <p class="slip-note alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Hitungan di DPP tidak konsisten untuk anggota ini. Periksa dulu sebelum slip dibagikan.</p>
    @endif
    {{-- Catatan ini harus tetap tercetak di setiap slip: jangan dikira angka yang diterima bersih. --}}
    <p class="slip-note">Rincian menurut DPP. Belum termasuk potongan satuan (koperasi, Persit, dan lain-lain) yang diisi di pembukuan.</p>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4 text-xs">
        <div class="text-muted">Sumber
            <div class="mt-0.5 font-mono text-[13px] text-ink">{{ $r['file'] }} · hal {{ $r['halaman'] }} · no. {{ $r['no_urut'] }}</div>
        </div>
        <div class="min-w-[180px] text-center">
            <div>{{ $pg('kota', '[Kota]') }}, {{ now()->translatedFormat('j F Y') }}</div>
            <div>Juru Bayar</div>
            <div class="h-12"></div>
            <div class="border-t border-ink pt-1 font-semibold">{{ $pg('juru', '[Nama Juru Bayar]') }}</div>
            <div class="text-muted">{{ $pg('juru_nrp', '[Pangkat / NRP]') }}</div>
        </div>
    </div>
</article>
