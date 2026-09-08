@extends('student.layout')

@section('content')
    <h1>Selesai. Terima kasih.</h1>
    <p>Semua jawabanmu sudah tersimpan. Kamu boleh menutup halaman ini.</p>
    <p class="muted">Kalau ada yang ingin ditanyakan, sampaikan ke pengawas di ruanganmu.</p>

    @include('student.partials.report')
@endsection
