<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    @if(! $started)
        <meta http-equiv="refresh" content="10">
    @endif
    <title>QR rombongan — C-ECO</title>
    @vite(['resources/css/student.css', 'resources/js/student.js'])
</head>
<body>
<header class="bar">
    <div>
        <strong>C-ECO</strong>
        <span class="muted">{{ $group->school->name }}</span>
    </div>
    <span class="who">{{ $group->room }}<br>{{ $group->starts_at->timezone(config('app.timezone'))->format('d M H:i') }}</span>
</header>

{{-- id qr-live, bukan qr-root: skrip bundel lama mencari qr-root dan jangan ikut mem-poll. --}}
<main class="wrap" id="qr-live"
      data-poll-url="{{ route('proctor.qr.current', $group) }}"
      data-started="{{ $started ? '1' : '0' }}"
      data-token="{{ $started ? ($token ?? '') : '' }}">
    <p class="progress" id="qr-progress" @if(! $started) hidden @endif>
        <span id="qr-count">{{ $opened }}</span> / {{ $total }} siswa sudah memuat
    </p>

    <div id="qr-wait" @if($started) hidden @endif>
        <h1>Kartu QR belum dibuka</h1>
        <p>Jam pelaksanaan ditetapkan operator. HP pengawas tidak dipakai sebagai jam. Kode tidak ditampilkan sebelum waktu server.</p>
        @if($opens_label)
            <p><strong>Mulai pukul {{ $opens_label }}</strong></p>
        @endif
        <p class="muted">Sekarang pukul <span id="qr-now">{{ $now_label }}</span> (server). Halaman terbuka sendiri setelah jam itu.</p>
    </div>

    <div id="qr-panel" @if(! $started) hidden @endif>
        @if($started && $session)
            <div class="qr" id="qr-svg">{!! $qr !!}</div>
            <p class="token" id="qr-token">{{ $spaced }}</p>
            <p class="muted" id="qr-hint">Sodorkan ke siswa. Begitu halaman siswa terbuka, kode berikutnya muncul sendiri.</p>
        @elseif($started)
            <p class="done" id="qr-done">Semua token rombongan ini sudah terpakai.</p>
        @endif
    </div>

    <p class="muted">Paket: {{ $group->testConfig->name }}</p>
    <p><a href="{{ url('/admin/exam-groups') }}">Kembali ke jadwal</a></p>
</main>
<script src="{{ asset('js/proctor-qr.js') }}"></script>
</body>
</html>
