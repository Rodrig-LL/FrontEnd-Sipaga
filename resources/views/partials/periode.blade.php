{{-- Pemilih periode: salah satu proses gaji yang sudah selesai. --}}
@php
    $daftarProses = \App\Support\DataGaji::daftarProses();
    $prosesAktif = \App\Support\DataGaji::proses();
@endphp
@if ($daftarProses->isNotEmpty())
    <form method="post" action="{{ route('periode') }}" class="fl">
        @csrf
        <label for="periode">Periode</label>
        <select id="periode" name="proses" class="inp h-10 min-w-[200px] text-sm" x-data x-on:change="$el.form.submit()">
            @foreach ($daftarProses as $d)
                <option value="{{ $d->id }}" @selected($d->id === $prosesAktif?->id)>{{ mb_convert_case($d->bulan, MB_CASE_TITLE) }} · diproses {{ $d->selesai_at?->translatedFormat('j M H:i') }}</option>
            @endforeach
        </select>
        <noscript><button type="submit" class="btn mt-1">Terapkan</button></noscript>
    </form>
@endif
