{{-- Lambang instansi dari public/img/logo-instansi.png. Opsional: $ukuran (kelas tinggi/lebar kotak), $monogram (kelas kotak cadangan) --}}
@php
    $ada = file_exists(public_path('img/logo-instansi.png'));
    $ukuran = $ukuran ?? 'h-10 w-12';
@endphp
@if ($ada)
    <img src="{{ asset('img/logo-instansi.png') }}" alt="Lambang Vijaya Kusuma" class="{{ $ukuran }} flex-none rounded-md bg-white object-contain p-0.5">
@else
    <div class="flex {{ $ukuran }} flex-none items-center justify-center rounded-lg bg-gold text-sm font-semibold text-side" aria-hidden="true">SG</div>
@endif
