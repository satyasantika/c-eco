<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>C-ECO</title>
    {{-- Hanya bundel sisi siswa. Aset Filament tidak pernah dimuat di sini. --}}
    @vite(['resources/css/student.css', 'resources/js/student.js'])
</head>
<body>
<header class="bar">
    <strong>C-ECO</strong>
    <span class="who">{{ $session->participant->display_name }}<br>{{ $session->participant->class_name }}</span>
</header>

<main class="wrap">
    @yield('content')
</main>

@yield('sticky')
</body>
</html>
