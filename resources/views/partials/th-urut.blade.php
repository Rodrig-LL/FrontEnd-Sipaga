{{-- Judul kolom yang bisa diklik untuk mengurutkan. Wajib: $kode, $label, $urut, $arah (urutan aktif), $bawaan (arah saat kolom ini baru dipilih). Opsional: $kelas.
     Mengklik kolom yang sedang aktif membalik arahnya; kolom lain dimulai dari arah bawaannya. Pindah halaman ke 1 karena urutannya berubah. --}}
@php
    $dipakai = $urut === $kode;
    $berikut = $dipakai ? ($arah === 'naik' ? 'turun' : 'naik') : $bawaan;
    $tujuan = request()->fullUrlWithQuery(['urut' => $kode, 'arah' => $berikut, 'halaman' => null]);
@endphp
<th scope="col" class="{{ $kelas ?? '' }}" @if ($dipakai) aria-sort="{{ $arah === 'naik' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ $tujuan }}" class="inline-flex items-center gap-1 text-body no-underline hover:underline" title="Urutkan menurut {{ $label }}">{{ $label }}<span aria-hidden="true" class="{{ $dipakai ? 'text-brand' : 'text-[#8A8D85]' }}">{{ $dipakai ? ($arah === 'naik' ? '▲' : '▼') : '↕' }}</span></a>
</th>
