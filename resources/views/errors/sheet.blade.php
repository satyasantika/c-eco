@php
    /** @var \Throwable $exception */
    $page = \App\Support\HttpErrorPage::from($exception, request());
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">
    <title>{{ $page->code }} — {{ $page->title }} · C-ECO</title>
    {{-- Berkas statis di public/, bukan Vite: lolos CSP rute siswa dan tetap ada saat build gagal. --}}
    <link rel="stylesheet" href="{{ asset('css/error.css') }}?v=1">
</head>
<body class="error-sheet">
<div class="frame">
    <div class="spine" aria-hidden="true"></div>
    <main class="sheet">
        <p class="wordmark">C-ECO</p>

        <div class="stamp">
            <svg viewBox="0 0 96 72" width="72" height="56" aria-hidden="true">
                <rect x="16" y="8" width="68" height="56" fill="#fffcf7" stroke="#1c5d3a" stroke-width="2"/>
                <path d="M16 8h18L16 24z" fill="#e4eee7" stroke="#1c5d3a" stroke-width="2"/>
                <line x1="28" y1="32" x2="72" y2="32" stroke="#c5d2c8" stroke-width="2"/>
                <line x1="28" y1="42" x2="64" y2="42" stroke="#c5d2c8" stroke-width="2"/>
                <line x1="28" y1="52" x2="56" y2="52" stroke="#c5d2c8" stroke-width="2"/>
            </svg>
            <p class="code">{{ $page->code }}</p>
        </div>

        <h1>{{ $page->title }}</h1>
        <p class="lede">{{ $page->lede }}</p>
        <p class="next">{{ $page->next }}</p>

        @if ($page->detail)
            <div class="keterangan" role="status">
                <span class="label">Keterangan</span>
                <p>{{ $page->detail }}</p>
            </div>
        @endif

        <dl>
            <dt>Kode</dt>
            <dd>{{ $page->code }}</dd>
            <dt>Permintaan</dt>
            <dd>{{ $page->method }} {{ $page->path }}</dd>
            <dt>Waktu server</dt>
            <dd>{{ $page->happenedAt }}</dd>
        </dl>

        <div class="actions">
            @if ($page->student)
                <a class="primary" href="{{ url('/') }}">Ketik token di halaman depan</a>
            @elseif ($page->staff)
                <a class="primary" href="{{ url('/admin') }}">Buka panel</a>
                <a class="text" href="{{ url('/') }}">Halaman depan</a>
            @else
                <a class="primary" href="{{ url('/') }}">Halaman depan</a>
                <a class="text" href="{{ url('/admin/login') }}">Masuk panel</a>
            @endif
        </div>

        @if ($page->debug)
            <p class="debug">{{ $page->debug }}</p>
        @endif
    </main>
</div>
</body>
</html>
