@extends('student.layout')

@push('head')
    <meta http-equiv="refresh" content="{{ $reloadSeconds }}">
@endpush

@section('content')
    <h1>Tes belum dimulai</h1>
    <p>Jam pelaksanaan ditetapkan operator. HP tidak dipakai sebagai jam. Halaman ini menunggu waktu server.</p>

    @if($opensAt)
        <p><strong>Mulai pukul {{ $opensAt->timezone(config('app.timezone'))->format('H:i') }}</strong>
            · {{ $opensAt->timezone(config('app.timezone'))->translatedFormat('l, d M Y') }}</p>
    @endif

    <p class="muted">Sekarang pukul {{ $serverNow->timezone(config('app.timezone'))->format('H:i:s') }} (server).
        Halaman terbuka sendiri setelah jam itu.</p>

    @if($session->examGroup)
        <p class="muted">{{ $session->examGroup->school->name ?? '' }} · {{ $session->examGroup->room }}</p>
    @endif
@endsection
