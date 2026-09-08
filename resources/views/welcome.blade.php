<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>C-ECO — Tes berpikir kreatif ekonomi</title>
    @vite(['resources/css/site.css'])
</head>
<body class="site">
<div class="site-frame">
    <div class="site-spine" aria-hidden="true"></div>
    <div class="site-sheet">
        <p class="wordmark">C-ECO</p>
        <p class="lede">Tes berpikir kreatif ekonomi. Soal menyesuaikan jawabanmu, satu per satu, di HP sendiri.</p>
        <p class="meta">Penelitian Unggulan Universitas Siliwangi · 2026. Bukan nilai rapor.</p>

        <div class="doors">
            <section class="door" aria-labelledby="siswa-judul">
                <h2 id="siswa-judul">Siswa</h2>
                <p>Pindai kode QR yang disodorkan pengawas, atau ketik delapan huruf dari slip cadangan.</p>

                <form method="POST" action="{{ route('landing.enter') }}">
                    @csrf
                    <label class="field" for="token">Token</label>
                    <input class="token"
                           id="token"
                           name="token"
                           type="text"
                           inputmode="text"
                           autocomplete="off"
                           autocapitalize="characters"
                           spellcheck="false"
                           maxlength="12"
                           value="{{ old('token') }}"
                           aria-invalid="{{ $errors->has('token') ? 'true' : 'false' }}"
                           required>
                    @error('token')
                        <p class="err" role="alert">{{ $message }}</p>
                    @enderror
                    <div class="actions">
                        <button class="primary" type="submit">Buka tes</button>
                    </div>
                </form>
            </section>

            <section class="door door-staff" aria-labelledby="staf-judul">
                <h2 id="staf-judul">Pengawas dan staf</h2>
                <p>Monitor, token, dan ekspor. Bukan untuk siswa.</p>
                <a class="text" href="{{ route('filament.admin.auth.login') }}">Masuk panel</a>
            </section>
        </div>

        <p class="foot">Kuota tes sekitar 200 KB. Tidak ada video. Kalau HP mati, buka lagi tautan di slip.</p>
    </div>
</div>
</body>
</html>
