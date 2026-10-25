<section class="card flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2>{{ count($hasil['perubahan']) }} gaji berubah dari pembukuan bulan lalu</h2>
        <span class="muted">Selisih bersih {{ rp(array_sum(array_map(fn ($r) => $r['baru'] - $r['lama'], $hasil['perubahan']))) }}</span>
    </div>
    <p class="note">Pembukuan baru selalu memakai angka DPP (JML DIBAYARKAN). Tidak ada pilihan angka lama di sini; kalau ada angka yang janggal, periksa di Temuan atau di aplikasi gaji, lalu proses ulang.</p>
    <div class="overflow-x-auto">
        <table class="t min-w-[800px]">
            <thead><tr><th scope="col">Personel</th><th scope="col" class="r">Baris buku</th><th scope="col" class="r">Gaji lama</th><th scope="col" class="r">Menurut DPP</th><th scope="col" class="r">Selisih</th></tr></thead>
            <tbody>
                @forelse ($hal as $r)
                    @php $sel = $r['baru'] - $r['lama']; @endphp
                    <tr>
                        <td><b>{{ $r['nama'] }}</b><div class="sm">{{ $r['pangkat'] }} · <span class="font-mono">{{ $r['nrp'] }}</span></div></td>
                        <td class="r num">{{ $r['baris'] }}</td>
                        <td class="r num text-[#4A4D46]">{{ rp($r['lama']) }}</td>
                        <td class="r num"><b>{{ rp($r['baru']) }}</b></td>
                        <td class="r num">{{ $sel > 0 ? '+' : '' }}{{ rp($sel) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center muted">Tidak ada gaji yang berubah.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'perubahan'])
</section>
