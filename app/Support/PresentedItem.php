<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Butir siap kirim ke klien.
 *
 * Opsi sudah teracak dan hanya membawa label tampilan — tidak ada is_key,
 * tidak ada label asli, tidak ada petunjuk benar/salah (SPEC §7).
 */
final readonly class PresentedItem
{
    /**
     * @param  list<array{label: string, body_html: string}>  $options
     */
    public function __construct(
        public int $sequence,
        public string $stemHtml,
        public ?string $mediaPath,
        public array $options,
    ) {}

    /**
     * @return array{sequence: int, stem_html: string, media_path: ?string, options: list<array{label: string, body_html: string}>}
     */
    public function toArray(): array
    {
        return [
            'sequence' => $this->sequence,
            'stem_html' => $this->stemHtml,
            'media_path' => $this->mediaPath,
            'options' => $this->options,
        ];
    }
}
