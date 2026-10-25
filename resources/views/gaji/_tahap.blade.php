{{-- Stepper 5 langkah proses gaji. Wajib: $langkah (aktif), $tautan [1..5 => url|null]. Tautan null = belum bisa dibuka. --}}
@php $label = ['Unggah berkas', 'Periksa hasil baca', 'Cocokkan data', 'Verifikasi selisih', 'Unduh hasil']; @endphp
<ol class="steps flex flex-wrap gap-1.5" aria-label="Tahapan proses">
    @foreach ($label as $i => $nama)
        @php
            $no = $i + 1; $kelas = $no < $langkah ? 'done' : ($no === $langkah ? 'now' : 'next');
            $isi = '<span class="dot '.$kelas.'" aria-hidden="true">'.($no < $langkah ? view('partials.ikon', ['n' => 'ok', 'u' => 13, 'w' => 3])->render() : $no).'</span>'.e($nama);
        @endphp
        <li class="{{ $no === $langkah ? 'cur' : '' }} {{ empty($tautan[$no]) ? 'opacity-60' : '' }}" @if ($no === $langkah) aria-current="step" @endif>
            @if (! empty($tautan[$no]))
                <a href="{{ $tautan[$no] }}" class="flex items-center gap-2.5 text-inherit no-underline">{!! $isi !!}</a>
            @else
                <span class="flex items-center gap-2.5" aria-disabled="true">{!! $isi !!}</span>
            @endif
        </li>
    @endforeach
</ol>
