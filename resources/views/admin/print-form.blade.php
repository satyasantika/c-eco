<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Bentuk cetak — {{ $config->name }}</title>
    <style>
        /* Berdiri sendiri: tidak satu pun berkas dari luar (aturan R1).
           Halaman ini dicetak H-1 dan dibawa dalam map; kalau ia butuh
           jaringan untuk tampil benar, ia gagal justru pada saat dipakai. */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 12mm;
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.45;
            color: #000;
        }

        .toolbar { margin-bottom: 8mm; }

        .toolbar button {
            font-size: 14px;
            padding: 8px 16px;
            border: 0;
            border-radius: 6px;
            background: #1c5d3a;
            color: #fff;
            cursor: pointer;
        }

        h1 { font-size: 15pt; margin: 0 0 1mm; }

        .meta { font-size: 9pt; color: #444; margin: 0 0 6mm; }

        .identitas {
            border: 1px solid #000;
            padding: 4mm;
            margin-bottom: 6mm;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3mm 8mm;
            font-size: 10pt;
        }

        .identitas span { display: block; border-bottom: 1px solid #999; padding-top: 5mm; }

        .butir {
            margin-bottom: 5mm;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .butir > .nomor { font-weight: 700; }
        .butir .stem { margin: 1mm 0 2mm; }
        .butir .stem :is(p, table) { margin: 0 0 1mm; }
        .butir table { border-collapse: collapse; font-size: 9pt; }
        .butir table :is(td, th) { border: 1px solid #666; padding: 1mm 2mm; }

        ol.opsi { margin: 0; padding-left: 8mm; list-style: upper-alpha; }
        ol.opsi li { margin-bottom: 0.5mm; }

        /* Lembar jawaban di halaman sendiri: dikumpulkan terpisah dari soal
           supaya satu berkas soal bisa dipakai ulang antar kelas. */
        .lembar-jawaban { page-break-before: always; }

        table.jawaban { border-collapse: collapse; width: 100%; font-size: 11pt; }
        table.jawaban :is(td, th) { border: 1px solid #000; padding: 2.5mm; text-align: center; }
        table.jawaban th:first-child, table.jawaban td:first-child { width: 14mm; }

        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak</button>
</div>

<h1>C-ECO — {{ $config->name }}</h1>
<p class="meta">
    Bentuk linear tetap, {{ $items->count() }} butir, disusun
    {{ now()->translatedFormat('j F Y H:i') }}.
    Urutan butir sama persis dengan mode linear di layar.
</p>

<div class="identitas">
    <label>Nama<span></span></label>
    <label>Kelas<span></span></label>
    <label>Sekolah<span></span></label>
    <label>Token (salin dari slip)<span></span></label>
</div>

@foreach ($items as $index => $item)
    <div class="butir">
        <div class="nomor">{{ $index + 1 }}. <span style="font-weight:400;font-size:9pt;color:#555;">[{{ $item->code }} — {{ $item->dimension->code }}]</span></div>
        <div class="stem">{!! $item->stem_html !!}</div>
        <ol class="opsi">
            @foreach ($item->options as $option)
                <li>{!! $option->body_html !!}</li>
            @endforeach
        </ol>
    </div>
@endforeach

<div class="lembar-jawaban">
    <h1>Lembar Jawaban</h1>
    <div class="identitas">
        <label>Nama<span></span></label>
        <label>Kelas<span></span></label>
        <label>Sekolah<span></span></label>
        <label>Token (salin dari slip)<span></span></label>
    </div>

    <table class="jawaban">
        <thead>
        <tr>
            <th>No</th><th>A</th><th>B</th><th>C</th><th>D</th><th>E</th>
        </tr>
        </thead>
        <tbody>
        @for ($n = 1; $n <= $items->count(); $n++)
            <tr>
                <td>{{ $n }}</td><td></td><td></td><td></td><td></td><td></td>
            </tr>
        @endfor
        </tbody>
    </table>
</div>
</body>
</html>
