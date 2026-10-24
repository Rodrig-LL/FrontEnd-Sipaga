{{-- Pilihan periode. Opsional: $tambah (daftar periode tambahan) --}}
@php
    $periodeAktif = session('periode', 'Oktober 2026');
    $daftarPeriode = array_merge(\App\Support\Demo::PERIODE, $tambah ?? []);
@endphp
<form method="post" action="{{ route('periode') }}" class="fl">
    @csrf
    <label for="periode">Periode</label>
    <select id="periode" name="periode" class="inp h-10 min-w-[170px] text-sm" x-data x-on:change="$el.form.submit()">
        @foreach ($daftarPeriode as $p)
            <option @selected($p === $periodeAktif)>{{ $p }}</option>
        @endforeach
    </select>
    <noscript><button type="submit" class="btn mt-1">Terapkan</button></noscript>
</form>
