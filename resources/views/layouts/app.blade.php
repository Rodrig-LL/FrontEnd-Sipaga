<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') – SIPAGA</title>
    @include('partials.ikon-tab')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="{{ \App\Models\Pengaturan::ambil('lega') ? 'lega' : '' }}">
<div class="flex min-h-screen flex-wrap">
    <div class="no-print flex-[1_1_216px] bg-side text-[#E6E9E1]">
        @include('partials.sidebar')
    </div>

    <main class="flex min-w-0 flex-[999_1_560px] flex-col gap-4 px-7 pb-8 pt-6 print:p-0">
        @yield('konten')
    </main>
</div>

@if (session('ok'))
    <div x-data="{ tampil: true }" x-init="setTimeout(() => tampil = false, 4500)" x-show="tampil" x-cloak role="status" aria-live="polite"
         class="no-print fixed bottom-6 left-1/2 z-50 max-w-[90vw] -translate-x-1/2 rounded-lg bg-ink px-5 py-3 text-[15px] text-white shadow-lg">
        {{ session('ok') }}
    </div>
@endif
</body>
</html>
