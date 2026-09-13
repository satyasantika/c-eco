<x-filament-panels::page>
    <article class="ceco-folio">
        <header class="ceco-folio-head">
            <h2>Berkas yang boleh diunggah</h2>
            <p>Hanya .json, {{ $this->maxMegabytes() }} MB, UTF-8. Ditolak: {{ implode(', ', $this->rejectedExtensions()) }}.</p>
        </header>

        <ol class="ceco-folio-gates">
            @foreach ($this->ruleSummaries() as $gate)
                <li>
                    <strong>{{ $gate['title'] }}</strong>
                    <span>{{ $gate['line'] }}</span>
                </li>
            @endforeach
        </ol>

        <details class="ceco-folio-more">
            <summary>Aturan lengkap</summary>
            <ul>
                @foreach ($this->rules() as $rule)
                    <li>{{ $rule }}</li>
                @endforeach
            </ul>
        </details>

        <section class="ceco-folio-examples">
            <h2>Dua butir contoh</h2>
            <p>Kode X-91 dan X-92. Ganti jika sudah terpakai. Unduh berkas dari tombol di atas, lalu sunting.</p>

            @foreach ($this->sampleSlips() as $item)
                <article class="ceco-folio-item">
                    <header>
                        <strong>{{ $item['code'] }}</strong>
                        <span>{{ $item['dimension_label'] }}</span>
                    </header>
                    <div class="ceco-folio-stem">{!! $item['stem_html'] !!}</div>
                    <ol class="ceco-folio-options">
                        @foreach ($item['options'] as $option)
                            <li @class(['ceco-folio-option', 'is-key' => $option['is_key']])>
                                <span class="ceco-folio-letter">{{ $option['label'] }}</span>
                                <div>{!! $option['body_html'] !!}</div>
                            </li>
                        @endforeach
                    </ol>
                </article>
            @endforeach
        </section>

        <details class="ceco-folio-more">
            <summary>Isi berkas JSON</summary>
            <pre>{{ $this->sampleJson() }}</pre>
        </details>
    </article>
</x-filament-panels::page>
