<section class="grid gap-3 md:grid-cols-3">
    <div class="kpi"><div class="l">Cocok otomatis</div><div class="v !text-[28px]">486</div><div class="s">Tidak perlu diperiksa</div></div>
    <div class="kpi"><div class="l">Ada selisih angka</div><div class="v !text-[28px]">19</div><div class="s">Diputuskan di langkah 4</div></div>
    <div class="kpi border-ink"><div class="l alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])Tidak cocok</div><div class="v !text-[28px]">{{ 7 - $fix }}</div><div class="s">Selesaikan di bawah ini</div></div>
</section>
<section class="card">
    <h2 class="mb-3">Data yang tidak cocok ({{ $fix }} dari 7 selesai)</h2>
    <div class="overflow-x-auto">
        <table class="t min-w-[860px]">
            <thead><tr><th scope="col">Personel</th><th scope="col">Jenis</th><th scope="col">Masalah</th><th scope="col" class="r">Tindakan</th></tr></thead>
            <tbody>
                @foreach ($tidakCocok as $m)
                    @php $selesai = ! empty($st['fixed'][$m['key']]); @endphp
                    <tr>
                        <td><b>{{ $m['nama'] }}</b><div class="sm">{{ $m['pangkat'] }} · <span class="font-mono">{{ $m['id'] }}</span></div></td>
                        <td>{{ $m['jenis'] }}</td>
                        <td>{{ $m['masalah'] }}</td>
                        <td class="r act">
                            <form method="post" action="{{ route('proses.aksi') }}" class="inline-flex items-center gap-1">
                                @csrf
                                <input type="hidden" name="key" value="{{ $m['key'] }}">
                                @if ($selesai)
                                    <span class="ok-t">@include('partials.ikon', ['n' => 'ok', 'u' => 16, 'w' => 2.6])Selesai</span>
                                    <button type="submit" name="do" value="unfix" class="btn-link">Ubah</button>
                                @else
                                    <button type="submit" name="do" value="fix" class="btn btn-o">{{ $m['aksi'] }}</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
