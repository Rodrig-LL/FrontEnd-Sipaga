{{-- Ikon tab browser: lambang instansi (public/img/logo-instansi.png) --}}
@if (file_exists(public_path('img/logo-instansi.png')))
    <link rel="icon" type="image/png" href="{{ asset('img/logo-instansi.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/logo-instansi.png') }}">
@endif
