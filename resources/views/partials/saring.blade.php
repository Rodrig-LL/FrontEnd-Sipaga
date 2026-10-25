{{-- Kotak cari + pilihan jenis + status. Wajib: $q, $jenis, $status, $jumlah, $opsi [[nilai, label]] --}}
<section class="filters" aria-label="Saring daftar">
    <form method="get" class="flex flex-1 flex-wrap items-end gap-3">
        @isset($jenis)<input type="hidden" name="jenis" value="{{ $jenis }}">@endisset
        <label class="fl min-w-[220px] flex-1">Cari
            <input class="inp" type="search" name="q" value="{{ $q }}" placeholder="Nama, NRP, atau NIP" autocomplete="off">
        </label>
        <label class="fl">Status
            <select class="inp min-w-[180px]" name="status" x-data x-on:change="$el.form.submit()">
                @foreach ($opsi as [$nilai, $label])
                    <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        @isset($urut)
        {{-- Pengurutan (opsional). Mengganti kriteria mengosongkan arah supaya arah bawaan kriteria baru yang dipakai. --}}
        @php $namaArah = ['nama' => ['A → Z', 'Z → A'], 'pangkat' => ['Terendah dulu', 'Tertinggi dulu'], 'jenis' => ['TNI dulu', 'PNS dulu']][$urut]; @endphp
        <label class="fl">Urutkan
            <select class="inp min-w-[170px]" name="urut" x-data x-on:change="$el.form.elements.arah.disabled = true; $el.form.submit()">
                <option value="nama" @selected($urut === 'nama')>Nama</option>
                <option value="pangkat" @selected($urut === 'pangkat')>Pangkat / Gol.</option>
                <option value="jenis" @selected($urut === 'jenis')>Jenis keanggotaan</option>
            </select>
        </label>
        <label class="fl">Arah
            <select class="inp min-w-[150px]" name="arah" x-data x-on:change="$el.form.submit()">
                <option value="naik" @selected($arah === 'naik')>{{ $namaArah[0] }}</option>
                <option value="turun" @selected($arah === 'turun')>{{ $namaArah[1] }}</option>
            </select>
        </label>
        @endisset
        <button type="submit" class="btn btn-p h-11">Cari</button>
    </form>
    @isset($jumlah)
    <div class="fl" role="group" aria-label="Jenis personel">
        <span>Jenis</span>
        <div class="seg">
            <a href="{{ request()->fullUrlWithQuery(['jenis' => null, 'halaman' => null]) }}" aria-current="{{ $jenis === '' ? 'true' : 'false' }}">Semua ({{ $jumlah[''] }})</a>
            <a href="{{ request()->fullUrlWithQuery(['jenis' => 'TNI', 'halaman' => null]) }}" aria-current="{{ $jenis === 'TNI' ? 'true' : 'false' }}">TNI ({{ $jumlah['TNI'] }})</a>
            <a href="{{ request()->fullUrlWithQuery(['jenis' => 'PNS', 'halaman' => null]) }}" aria-current="{{ $jenis === 'PNS' ? 'true' : 'false' }}">PNS ({{ $jumlah['PNS'] }})</a>
        </div>
    </div>
    @endisset
</section>
