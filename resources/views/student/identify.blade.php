@extends('student.layout')

@section('content')
    <h1>Isi identitas</h1>
    <p>Kamu masuk lewat kode QR pengawas. Isi data di bawah ini sesuai kartu pelajar, lalu lanjut ke persetujuan tes.</p>

    @if($session->examGroup)
        <p class="muted">{{ $session->examGroup->school->name ?? '' }} · {{ $session->examGroup->room }} · {{ $session->examGroup->starts_at?->format('d M Y H:i') }}</p>
    @endif

    <form method="POST" action="{{ route('student.identify', $session->access_token) }}">
        @csrf

        <div class="field stack">
            <label for="student_code">Nomor induk siswa</label>
            <input id="student_code" name="student_code" type="text" inputmode="numeric" autocomplete="off" required maxlength="32" value="{{ old('student_code') }}">
            @error('student_code')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field stack">
            <label for="display_name">Nama lengkap</label>
            <input id="display_name" name="display_name" type="text" autocomplete="name" required maxlength="255" value="{{ old('display_name') }}">
            @error('display_name')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field stack">
            <label for="grade">Jenjang</label>
            <select id="grade" name="grade" required>
                <option value="">Pilih jenjang</option>
                @foreach(['X' => 'X', 'XI' => 'XI', 'XII' => 'XII'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('grade') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('grade')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field stack">
            <label for="class_name">Kelas</label>
            <input id="class_name" name="class_name" type="text" required maxlength="64" placeholder="contoh: IPS 1" value="{{ old('class_name') }}">
            <p class="muted">Tuliskan nama kelas di sekolahmu. Jenjang tidak perlu diulang.</p>
            @error('class_name')<p class="error">{{ $message }}</p>@enderror
        </div>
@endsection

@section('sticky')
        <div class="sticky">
            <div class="inner">
                <button type="submit">Lanjut</button>
            </div>
        </div>
    </form>
@endsection
