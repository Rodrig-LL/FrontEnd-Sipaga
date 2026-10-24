<section class="card flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h2>{{ $putus }} dari 19 selisih sudah diputuskan</h2>
        <form method="post" action="{{ route('proses.aksi', ['halaman' => $hal->currentPage()]) }}">
            @csrf
            <input type="hidden" name="do" value="allnew">
            <button type="submit" class="btn">Pakai softfile untuk semua di halaman ini</button>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="t min-w-[940px]">
            <thead>
                <tr>
                    <th scope="col">Personel</th><th scope="col">Jenis</th><th scope="col">Komponen</th>
                    <th scope="col" class="r">Angka lama</th><th scope="col" class="r">Softfile</th><th scope="col" class="r">Selisih</th><th scope="col" class="r">Keputusan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($hal as $r)
                    @php $c = $st['choice'][$r['key']] ?? null; @endphp
                    <tr>
                        <td><b>{{ $r['nama'] }}</b><div class="sm">{{ $r['pangkat'] }} · <span class="font-mono">{{ $r['id'] }}</span></div></td>
                        <td>{{ $r['jenis'] }}</td>
                        <td>{{ $r['komponen'] }}</td>
                        <td class="r num text-[#4A4D46]">{{ $r['lama'] }}</td>
                        <td class="r num"><b>{{ $r['baru'] }}</b></td>
                        <td class="r num">{{ $r['selisih'] }}</td>
                        <td class="r act">
                            <form method="post" action="{{ route('proses.aksi', ['halaman' => $hal->currentPage()]) }}" class="inline-flex items-center gap-1.5">
                                @csrf
                                <input type="hidden" name="key" value="{{ $r['key'] }}">
                                @if ($c)
                                    <span class="ok-t">@include('partials.ikon', ['n' => 'ok', 'u' => 16, 'w' => 2.6]){{ $c === 'baru' ? 'Pakai softfile' : 'Pakai angka lama' }}</span>
                                    <button type="submit" name="do" value="batal" class="btn-link">Ubah</button>
                                @else
                                    <button type="submit" name="do" value="baru" class="btn btn-p">Pakai softfile</button>
                                    <button type="submit" name="do" value="lama" class="btn">Pakai lama</button>
                                @endif
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @include('partials.pager', ['label' => 'selisih'])
</section>
