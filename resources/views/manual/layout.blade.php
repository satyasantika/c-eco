<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — Panduan C-ECO</title>
    @vite(['resources/css/site.css'])
</head>
<body class="site">
<div class="site-frame">
    <div class="site-spine" aria-hidden="true"></div>
    <div class="site-sheet site-sheet--wide">
        <p class="manual-crumb"><a class="text" href="{{ route('landing') }}">C-ECO</a> / <a class="text" href="{{ route('manual.index') }}">Panduan Pengguna</a></p>

        @yield('content')

        <p class="foot">Tangkapan layar diambil dari aplikasi yang berjalan dengan data simulasi demo, bukan data siswa asli.</p>
    </div>
</div>
</body>
</html>
