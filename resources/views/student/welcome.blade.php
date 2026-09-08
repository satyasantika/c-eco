@extends('student.layout')

@section('content')
    <h1>Tes Berpikir Kreatif Ekonomi</h1>

    <p>Halo, <strong>{{ $session->participant->display_name }}</strong>. Sebelum mulai, baca dulu keterangan di bawah ini.</p>

    <h2>Cara kerjanya</h2>
    <p>Soal muncul satu per satu. Setiap soal punya lima pilihan, pilih satu lalu tekan <strong>Lanjut</strong>.
        Jawaban tersimpan begitu kamu menekan Lanjut, jadi kamu <strong>tidak bisa kembali</strong> ke soal sebelumnya.</p>
    <p>Panjang tes berbeda-beda untuk tiap orang karena soal menyesuaikan jawabanmu. Kira-kira 20–30 menit.</p>

    <div class="note">
        <p><strong>Tentang kuota</strong></p>
        {{-- Angka ini hasil pengukuran, bukan perkiraan: 187 KB tanpa kompresi
             di lingkungan uji, sekitar 80 KB lewat gzip produksi
             (docs/LOADTEST-FINDINGS.md). Dibulatkan ke atas untuk siswa. --}}
        <p>Tes ini memakai sekitar <strong>200 KB</strong> kuota data — kurang dari satu foto. Tidak ada video dan tidak ada gambar berat.</p>
    </div>

    <div class="note">
        <p><strong>Kalau koneksi putus</strong></p>
        <p>Jawabanmu disimpan di HP dan dikirim ulang otomatis begitu sinyal kembali.
            Kamu tidak perlu mengulang soal yang sudah dijawab. Kalau HP mati, buka lagi tautan yang sama.</p>
    </div>

    <h2>Persetujuan</h2>
    <p class="muted">Jawabanmu dipakai untuk menguji sistem tes ini, bukan untuk nilai rapor. Namamu tidak dipublikasikan.</p>

    <form method="POST" action="{{ route('student.consent', $session->access_token) }}">
        @csrf
        <div class="field">
            <label>
                <input type="checkbox" name="consent" value="1" required>
                <span>Saya bersedia mengikuti tes ini.</span>
            </label>
        </div>
@endsection

@section('sticky')
        <div class="sticky">
            <div class="inner">
                <button type="submit">Mulai</button>
            </div>
        </div>
    </form>
@endsection
