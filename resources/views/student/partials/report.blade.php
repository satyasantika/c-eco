{{--
  Wright map + tes informasi butir. Koordinat SVG dihitung di PHP; tidak ada
  atribut style supaya CSP sisi siswa (style-src 'self') tetap ketat.
--}}
<section class="result" aria-label="Umpan balik kemampuan">
    <div class="result-band">
        <p class="result-kicker">Gambaran kemampuan berpikir kreatif</p>
        <p class="result-label">{{ $feedback->category }}</p>
    </div>

    @if ($feedback->provisional)
        <div class="note">
            <p><strong>Bukan nilai rapor.</strong> Angka di halaman ini memakai parameter sementara. Berguna untuk melihat pola, bukan untuk rapor atau peringkat kelas.</p>
        </div>
    @endif

    <h2>Peta kemampuan (Wright map)</h2>
    <p>Titik <strong>kamu</strong> adalah perkiraan kemampuan berpikir kreatif ekonomi. Titik berwarna adalah tingkat kesukaran butir yang kamu kerjakan. Semakin ke atas, semakin sulit. Pita di sekitar kamu adalah kisaran ketelitian perkiraan.</p>

    <svg class="wright" viewBox="0 0 {{ \App\Services\StudentFeedbackBuilder::MAP_WIDTH }} {{ \App\Services\StudentFeedbackBuilder::MAP_TOP + \App\Services\StudentFeedbackBuilder::MAP_HEIGHT + 28 }}" role="img" aria-labelledby="wright-title">
        <title id="wright-title">Peta kemampuan: posisi kamu dan kesukaran butir pada satu garis</title>

        <text class="wright-pole" x="{{ \App\Services\StudentFeedbackBuilder::AXIS_X + 10 }}" y="16">Sulit</text>
        <text class="wright-pole" x="{{ \App\Services\StudentFeedbackBuilder::AXIS_X + 10 }}" y="{{ \App\Services\StudentFeedbackBuilder::MAP_TOP + \App\Services\StudentFeedbackBuilder::MAP_HEIGHT + 20 }}">Mudah</text>

        <line class="wright-axis"
              x1="{{ \App\Services\StudentFeedbackBuilder::AXIS_X }}"
              y1="{{ \App\Services\StudentFeedbackBuilder::MAP_TOP }}"
              x2="{{ \App\Services\StudentFeedbackBuilder::AXIS_X }}"
              y2="{{ \App\Services\StudentFeedbackBuilder::MAP_TOP + \App\Services\StudentFeedbackBuilder::MAP_HEIGHT }}"/>

        @foreach ($feedback->ticks as $tick)
            <line class="wright-tick"
                  x1="{{ \App\Services\StudentFeedbackBuilder::AXIS_X - 5 }}"
                  y1="{{ $tick['y'] }}"
                  x2="{{ \App\Services\StudentFeedbackBuilder::AXIS_X + 5 }}"
                  y2="{{ $tick['y'] }}"/>
            <text class="wright-label"
                  x="{{ \App\Services\StudentFeedbackBuilder::AXIS_X - 10 }}"
                  y="{{ $tick['y'] + 3.5 }}"
                  text-anchor="end">{{ $tick['label'] }}</text>
        @endforeach

        <rect class="wright-band"
              x="{{ \App\Services\StudentFeedbackBuilder::PERSON_X - 10 }}"
              y="{{ $feedback->bandY }}"
              width="20"
              height="{{ $feedback->bandHeight }}"
              rx="10"/>

        <circle class="wright-person"
                cx="{{ \App\Services\StudentFeedbackBuilder::PERSON_X }}"
                cy="{{ $feedback->personY }}"
                r="7"/>
        <text class="wright-person-label"
              x="{{ \App\Services\StudentFeedbackBuilder::PERSON_X }}"
              y="{{ $feedback->personY - 12 }}"
              text-anchor="middle">kamu</text>

        @foreach ($feedback->items as $item)
            <circle class="wright-item dim-{{ $item['dimension'] }}"
                    cx="{{ $item['map_x'] }}"
                    cy="{{ $item['map_y'] }}"
                    r="5">
                <title>Butir {{ $item['sequence'] }} · {{ $item['dimension_label'] }}</title>
            </circle>
        @endforeach
    </svg>

    <ul class="legend">
        @foreach ($feedback->dimensions as $dimension)
            <li>
                <span class="swatch dim-{{ $dimension['code'] }}"></span>
                {{ $dimension['label'] }}
            </li>
        @endforeach
    </ul>

    <h2>Tes informasi butir</h2>
    <p>Informasi butir mengukur seberapa banyak tiap soal menambah kejelasan perkiraan kemampuanmu. Butir yang kesulitannya dekat dengan kemampuanmu biasanya memberi informasi lebih besar.</p>
    <p>Jumlah informasi tes dari {{ $feedback->itemsAdministered }} butir: <strong>{{ number_format($feedback->testInformation, 2, ',', '') }}</strong>. Ketelitian perkiraan: <strong>{{ $feedback->precisionLabel }}</strong>.</p>

    <ul class="info-list">
        @foreach ($feedback->items as $item)
            <li>
                <span class="info-meta">Butir {{ $item['sequence'] }} · {{ $item['dimension_label'] }}</span>
                <span class="info-num">{{ number_format($item['information'], 2, ',', '') }}</span>
                <svg class="info-bar" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">
                    <rect class="info-track" x="0" y="0" width="100" height="10" rx="5"/>
                    <rect class="info-fill" x="0" y="0" width="{{ $item['bar_width'] }}" height="10" rx="5"/>
                </svg>
            </li>
        @endforeach
    </ul>
</section>
