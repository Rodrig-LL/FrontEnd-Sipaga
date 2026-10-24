{{-- Kotak cari + pilihan jenis + status. Wajib: $q, $jenis, $status, $jumlah, $opsi [[nilai, label]] --}}
<section class="filters" aria-label="Saring daftar">
    <form method="get" class="flex flex-1 flex-wrap items-end gap-3">
        <input type="hidden" name="jenis" value="{{ $jenis }}">
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
        <button type="submit" class="btn btn-p h-11">Cari</button>
    </form>
    <div class="fl" role="group" aria-label="Jenis personel">
        <span>Jenis</span>
        <div class="seg">
            <a href="{{ request()->fullUrlWithQuery(['jenis' => null, 'halaman' => null]) }}" aria-current="{{ $jenis === '' ? 'true' : 'false' }}">Semua ({{ $jumlah[''] }})</a>
            <a href="{{ request()->fullUrlWithQuery(['jenis' => 'TNI', 'halaman' => null]) }}" aria-current="{{ $jenis === 'TNI' ? 'true' : 'false' }}">TNI ({{ $jumlah['TNI'] }})</a>
            <a href="{{ request()->fullUrlWithQuery(['jenis' => 'PNS', 'halaman' => null]) }}" aria-current="{{ $jenis === 'PNS' ? 'true' : 'false' }}">PNS ({{ $jumlah['PNS'] }})</a>
        </div>
    </div>
</section>
