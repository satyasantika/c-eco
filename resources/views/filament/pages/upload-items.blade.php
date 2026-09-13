<x-filament-panels::page>
    <div class="ceco-folio">
        <section class="ceco-folio-paper">
            <h2>Berkas yang boleh diunggah</h2>
            <p>
                Hanya <strong>.{{ \App\Support\ItemPackageFormat::EXTENSION }}</strong>, paling besar {{ $this->maxMegabytes() }} MB, UTF-8. Ditolak: {{ implode(', ', $this->rejectedExtensions()) }}.
            </p>

            <ol class="ceco-folio-gates">
                @foreach ($this->ruleGroups() as $group)
                    <li>
                        <h3>{{ $group['title'] }}</h3>
                        <ul>
                            @foreach ($group['lines'] as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>

        <h2>Dua butir contoh</h2>
        <p>Kode X-91 dan X-92. Ganti jika sudah terpakai. Unduh berkas dari tombol di atas, lalu sunting.</p>

        <div class="ceco-folio-slips">
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
        </div>

        <details class="ceco-folio-source">
            <summary>Isi berkas JSON</summary>
            <pre>{{ $this->sampleJson() }}</pre>
        </details>
    </div>
</x-filament-panels::page>
