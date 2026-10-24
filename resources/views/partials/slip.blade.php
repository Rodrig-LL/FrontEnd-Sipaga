{{-- Dokumen slip gaji. Wajib: $r. Opsional: $pb (beri jeda halaman saat dicetak) --}}
@php $set = session('pengaturan', []); @endphp
<article class="slip {{ ! empty($pb) ? 'pb mb-4' : '' }}" aria-label="Slip gaji {{ $r['nama'] }}">
    <div class="flex items-center gap-4 border-b-2 border-ink pb-3.5">
        <div class="flex h-14 w-14 flex-none items-center justify-center border border-dashed border-[#A8A597] text-[10px] text-muted">LOGO</div>
        <div class="min-w-0 flex-1">
            <div class="text-[15px] font-semibold tracking-[.04em]">{{ ($set['instansi'] ?? '') !== '' ? $set['instansi'] : '[NAMA INSTANSI]' }}</div>
            <div class="text-xs text-muted">{{ ($set['alamat'] ?? '') !== '' ? $set['alamat'] : '[Alamat instansi]' }}</div>
        </div>
        <div class="text-right">
            <div class="text-base font-semibold tracking-[.08em]">SLIP GAJI</div>
            <div class="text-xs text-muted">Bulan Oktober 2026</div>
        </div>
    </div>

    <dl class="slip-meta">
        <div><dt>Nama</dt><dd><b>{{ $r['nama'] }}</b></dd></div>
        <div><dt>Jenis</dt><dd>{{ $r['jenis'] }}</dd></div>
        <div><dt>Pangkat / Gol.</dt><dd>{{ $r['pangkat'] }}</dd></div>
        <div><dt>NRP / NIP</dt><dd class="font-mono">{{ $r['nrp'] }}</dd></div>
        <div><dt>Bagian</dt><dd>{{ $r['bagian'] }}</dd></div>
        <div><dt>Status keluarga</dt><dd>{{ $r['kel'] }}</dd></div>
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
                @foreach ($r['ded'] as [$label, $nilai])
                    <tr><td>{{ $label }}</td><td>{{ rp($nilai) }}</td></tr>
                @endforeach
                <tr class="tot"><td>Jumlah potongan</td><td>{{ rp($r['pot']) }}</td></tr>
            </tbody></table>
        </section>
    </div>

    <div class="mt-5 flex flex-wrap items-baseline justify-between gap-2 rounded bg-paper px-4 py-3.5">
        <div>
            <div class="text-xs font-semibold tracking-[.08em] text-muted">DITERIMA BERSIH</div>
            <div class="mt-0.5 text-xs italic capitalize text-muted">{{ terbilang($r['bersih']) }} rupiah</div>
        </div>
        <div class="font-mono text-[22px] font-medium">Rp {{ rp($r['bersih']) }}</div>
    </div>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4 text-xs">
        <div class="text-muted">Kode verifikasi
            <div class="mt-0.5 font-mono text-[13px] text-ink">SPG-2610-{{ str_pad($r['n'], 5, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div class="min-w-[180px] text-center">
            <div>{{ ($set['kota'] ?? '') !== '' ? $set['kota'] : '[Kota]' }}, [tanggal terbit]</div>
            <div>Juru Bayar</div>
            <div class="h-12"></div>
            <div class="border-t border-ink pt-1 font-semibold">{{ ($set['juru'] ?? '') !== '' ? $set['juru'] : '[Nama Juru Bayar]' }}</div>
            <div class="text-muted">{{ ($set['juru_nrp'] ?? '') !== '' ? $set['juru_nrp'] : '[Pangkat / NRP]' }}</div>
        </div>
    </div>
</article>
