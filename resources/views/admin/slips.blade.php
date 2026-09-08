<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip token — {{ $config->name }}</title>
    <style>
        /* Halaman cetak berdiri sendiri: tidak memuat aset Filament maupun
           aset sisi siswa, dan tidak satu pun berkas dari luar (aturan R1). */
        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
            color: #000;
            background: #f4f5f7;
        }

        .toolbar {
            padding: 16px;
            background: #fff;
            border-bottom: 1px solid #ccc;
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
        }

        .toolbar button {
            font-size: 16px;
            padding: 10px 18px;
            border: 0;
            border-radius: 8px;
            background: #1c5d3a;
            color: #fff;
            cursor: pointer;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 10mm;
            background: #fff;
            display: grid;
            /* 2 kolom x 4 baris = 8 slip per halaman A4. */
            grid-template-columns: 1fr 1fr;
            grid-template-rows: repeat(4, 1fr);
            gap: 6mm;
        }

        .slip {
            border: 1px dashed #999;
            border-radius: 3mm;
            padding: 5mm;
            display: flex;
            gap: 5mm;
            align-items: center;
            /* Slip tidak boleh terpotong di antara dua halaman. */
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .slip .detail { flex: 1 1 auto; min-width: 0; }

        .slip .name {
            font-size: 13pt;
            font-weight: 700;
            margin: 0 0 1mm;
            overflow-wrap: anywhere;
        }

        .slip .class { font-size: 10pt; color: #444; margin: 0 0 3mm; }

        .slip .token {
            font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
            font-size: 20pt;
            font-weight: 700;
            letter-spacing: 3px;
            margin: 0 0 2mm;
        }

        .slip .url { font-size: 8pt; color: #444; margin: 0; overflow-wrap: anywhere; }
        .slip .qr { flex: 0 0 auto; }
        .slip .qr svg { display: block; }

        .empty { padding: 24px; text-align: center; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; width: auto; min-height: auto; box-shadow: none; padding: 0; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <div>
        <strong>{{ $config->name }}</strong> — {{ $slips->count() }} slip
        @if ($slips->isNotEmpty())
            ({{ (int) ceil($slips->count() / 8) }} halaman A4)
        @endif
    </div>
    <button type="button" onclick="window.print()">Cetak</button>
</div>

@if ($slips->isEmpty())
    <p class="empty">Belum ada token untuk konfigurasi ini. Jalankan <code>php artisan cat:issue-tokens</code>.</p>
@else
    @foreach ($slips->chunk(8) as $page)
        <div class="sheet">
            @foreach ($page as $slip)
                <div class="slip">
                    <div class="detail">
                        <p class="name">{{ $slip['session']->participant->display_name }}</p>
                        <p class="class">{{ $slip['session']->participant->class_name }} — {{ $slip['session']->participant->school->name }}</p>
                        <p class="token">{{ $slip['session']->access_token }}</p>
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
