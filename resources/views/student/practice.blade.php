@extends('student.layout')

@section('content')
    <p class="progress">Soal latihan — tidak dinilai</p>
    <h1>Coba dulu satu soal</h1>
    <p class="muted">Soal ini hanya untuk membiasakan diri. Jawaban apa pun tidak dihitung.</p>

    <div class="stem">
        <p>Warung Bu Ani kehabisan gula setiap akhir pekan, padahal pemasoknya hanya datang hari Senin.
            Apa langkah yang paling masuk akal untuk Bu Ani?</p>
    </div>

    <form method="POST" action="{{ route('student.test.begin', $session->access_token) }}" id="latihan">
        @csrf
        <ul class="options">
            @foreach ([
                'A' => 'Menutup warung setiap akhir pekan',
                'B' => 'Menambah stok gula sebelum akhir pekan',
                'C' => 'Menaikkan harga gula sepuluh kali lipat',
                'D' => 'Berhenti menjual gula selamanya',
                'E' => 'Menunggu pemasok datang sendiri',
            ] as $label => $body)
                <li>
                    <label class="option">
                        <input type="radio" name="latihan" value="{{ $label }}" required>
                        <span>
                            <span class="tag">{{ $label }}.</span>
                            <span class="body"><p>{{ $body }}</p></span>
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
@endsection

@section('sticky')
        <div class="sticky">
            <div class="inner">
                <button type="submit">Lanjut ke tes</button>
            </div>
        </div>
    </form>
@endsection
