<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Slip QR — {{ $group->school->name }} · {{ $group->room }}</title>
    <style>
        /* Halaman cetak berdiri sendiri, tanpa aset Filament atau berkas luar (aturan R1). */
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #000; background: #f4f5f7; }
        .toolbar { padding: 16px; background: #fff; border-bottom: 1px solid #ccc; display: flex; gap: 16px; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; }
        .toolbar p { margin: 4px 0 0; max-width: 70ch; }
        .toolbar button { font-size: 16px; padding: 10px 18px; border: 0; border-radius: 8px; background: #1c5d3a; color: #fff; cursor: pointer; }
        .warn { color: #8a4b00; }
        .sheet { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 10mm; background: #fff; display: grid; grid-template-columns: 1fr 1fr; grid-template-rows: repeat(4, 1fr); gap: 6mm; }
        .slip { border: 1px dashed #999; border-radius: 3mm; padding: 5mm; display: flex; gap: 5mm; align-items: center; break-inside: avoid; page-break-inside: avoid; }
        .slip.used { opacity: .45; }
        .slip .detail { flex: 1 1 auto; min-width: 0; }
        .slip .seat { font-size: 13pt; font-weight: 700; margin: 0 0 1mm; }
        .slip .where { font-size: 9.5pt; color: #444; margin: 0 0 2mm; }
        .slip .token { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; font-size: 17pt; font-weight: 700; letter-spacing: 2px; margin: 0 0 2mm; }
        .slip .opens { font-size: 8.5pt; margin: 0 0 1mm; }
        .slip .url { font-size: 7.5pt; color: #444; margin: 0; overflow-wrap: anywhere; }
        .slip .qr { flex: 0 0 auto; }
        .slip .qr svg { display: block; }
        .empty { padding: 24px; text-align: center; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: auto; padding: 0; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div>
        <strong>{{ $group->school->name }} · {{ $group->room }}</strong> — {{ $slips->count() }} slip
        @if ($slips->isNotEmpty())
            ({{ (int) ceil($slips->count() / 8) }} halaman A4)
        @endif
        <p>Mulai: <strong>{{ $opensLabel }}</strong> (waktu server). Sebelum jam itu, slip yang dipindai hanya menampilkan
            "Tes belum dimulai". Siswa belum bisa mengisi identitas atau mengerjakan tes.</p>
        <p>Satu slip untuk satu siswa. Token terkunci ke HP pertama yang mengisi identitas. Simpan slip di tempat aman sampai dibagikan.</p>
        <p class="warn">Kalau slip cetak dipakai, jangan sodorkan juga Kartu QR pengawas di ruang ini. Kartu itu menampilkan kursi yang
            mungkin sudah tercetak untuk siswa lain.</p>
        @if ($usedCount > 0)
            <p class="warn">{{ $usedCount }} kursi sudah dibuka atau dipakai (slip ditampilkan pudar). Jangan dibagikan lagi.</p>
        @endif
    </div>
    <button type="button" onclick="window.print()">Cetak</button>
</div>

@if ($slips->isEmpty())
    <p class="empty">Rombongan ini belum punya kursi.</p>
@else
    @foreach ($slips->chunk(8) as $page)
        <div class="sheet">
            @foreach ($page as $slip)
                <div class="slip @if($slip['used']) used @endif">
                    <div class="detail">
                        <p class="seat">Kursi {{ $slip['seat'] }} / {{ $slip['total'] }}</p>
                        <p class="where">{{ $group->school->name }} · {{ $group->room }}</p>
                        <p class="token">{{ $slip['spaced'] }}</p>
                        <p class="opens">Bisa dipakai mulai {{ $opensLabel }}</p>
                        <p class="url">{{ $slip['url'] }}</p>
                    </div>
                    <div class="qr">{!! $slip['qr'] !!}</div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif
</body>
</html>
