@extends('student.layout')

@section('content')
    <h1>Token sudah dipakai</h1>
    <p>Tautan ini sudah terikat ke HP peserta yang pertama kali mengisi identitas. Nama dan jawaban di sistem tetap milik peserta itu.</p>
    <p>Buka tes hanya dari HP itu. Jangan memakai token teman.</p>
    <p><strong>HP-mu mati atau rusak di tengah tes?</strong> Angkat tangan dan minta pengawas mengizinkan <strong>pindah HP</strong> untuk namamu. Setelah itu buka lagi tautan atau token yang sama di HP ini dalam {{ \App\Services\SeatReleaser::RELEASE_MINUTES }} menit; tes berlanjut dari soal terakhir.</p>
@endsection
