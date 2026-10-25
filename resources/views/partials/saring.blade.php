{{-- Kotak cari + status + jenis keanggotaan (bila ada $jumlah). Wajib: $q, $jenis, $status, $jumlah, $opsi [[nilai, label]]. Opsional: $urut, $arah (dibawa sebagai input tersembunyi) --}}
<section class="filters" aria-label="Saring daftar">
    <form method="get" class="flex flex-1 flex-wrap items-end gap-3">
        @if (isset($jenis) && ! isset($jumlah))<input type="hidden" name="jenis" value="{{ $jenis }}">@endif
        {{-- Pengurutan diatur lewat judul kolom tabel (partials/th-urut); di sini hanya dibawa supaya mencari/menyaring tidak mengubah urutan. --}}
        @isset($urut)<input type="hidden" name="urut" value="{{ $urut }}"><input type="hidden" name="arah" value="{{ $arah }}">@endisset
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
        @isset($jumlah)
        <label class="fl">Jenis keanggotaan
            <select class="inp min-w-[180px]" name="jenis" x-data x-on:change="$el.form.submit()">
                <option value="" @selected($jenis === '')>Semua ({{ $jumlah[''] }})</option>
                <option value="TNI" @selected($jenis === 'TNI')>TNI ({{ $jumlah['TNI'] }})</option>
                <option value="PNS" @selected($jenis === 'PNS')>PNS ({{ $jumlah['PNS'] }})</option>
            </select>
        </label>
        @endisset
        <button type="submit" class="btn btn-p h-11">Cari</button>
    </form>
</section>
