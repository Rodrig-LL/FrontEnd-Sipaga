<section class="grid gap-3 md:grid-cols-2">
    <div class="card"><div class="muted">Softfile TNI</div><div class="mt-1 text-xl font-semibold"><span class="font-mono">318</span> baris terbaca · <span class="font-mono">0</span> gagal</div></div>
    <div class="card"><div class="muted">Softfile PNS</div><div class="mt-1 text-xl font-semibold"><span class="font-mono">194</span> baris terbaca · <span class="font-mono">0</span> gagal</div></div>
</section>
<section class="card">
    <h2>Contoh 5 baris pertama</h2>
    <p class="muted mb-3 mt-1">Cocokkan sekilas dengan berkas asli. Kalau kolomnya tertukar, kembali ke langkah 1 dan unggah ulang.</p>
    <div class="overflow-x-auto">
        <table class="t min-w-[720px]">
            <thead><tr><th scope="col">Sumber</th><th scope="col">Nama</th><th scope="col">NRP / NIP</th><th scope="col" class="r">Gaji pokok</th><th scope="col" class="r">Penghasilan kotor</th></tr></thead>
            <tbody>
                @foreach ([['TNI · baris 1', 'Ahmad Fauzi', '11•••2214', '4.910.000', '7.842.300'], ['TNI · baris 2', 'Teguh Santoso', '11•••5506', '4.418.000', '6.704.500'], ['TNI · baris 3', 'Rudi Setiawan', '21•••3301', '3.984.000', '5.960.800'], ['PNS · baris 1', 'Dewi Anggraini', '1982•••3007', '4.120.900', '6.125.400'], ['PNS · baris 2', 'Wulan Sari', '1991•••1504', '3.183.400', '4.906.300']] as $p)
                    <tr><td class="muted">{{ $p[0] }}</td><td><b>{{ $p[1] }}</b></td><td class="num">{{ $p[2] }}</td><td class="r num">{{ $p[3] }}</td><td class="r num">{{ $p[4] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
