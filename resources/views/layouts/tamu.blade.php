<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') – SIPAGA</title>
    @include('partials.ikon-tab')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="flex min-h-screen items-center justify-center p-6">
    @yield('konten')
</div>
</body>
</html>
