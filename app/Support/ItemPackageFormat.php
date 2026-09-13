<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\ItemPackageImporter;

/**
 * Kontrak berkas unggah soal. Satu sumber untuk panel, contoh unduhan, dan uji.
 */
final class ItemPackageFormat
{
    public const EXTENSION = 'json';

    public const MAX_KILOBYTES = 2048;

    /** @var list<string> */
    public const MIME_TYPES = [
        'application/json',
        'text/json',
        'text/plain',
        'application/octet-stream',
    ];

    public const EXAMPLE_FILENAME = 'contoh-paket-soal.json';

    public static function maxMegabytes(): int
    {
        return (int) ceil(self::MAX_KILOBYTES / 1024);
    }

    /** @return list<string> */
    public static function rejectedExtensions(): array
    {
        return ['.docx', '.doc', '.xlsx', '.xls', '.csv', '.pdf', '.zip', '.xml'];
    }

    /** @return list<string> */
    public static function rules(): array
    {
        $grades = implode(', ', ItemPackageImporter::GRADES);
        $dimensions = implode(', ', ItemPackageImporter::DIMENSIONS);
        $max = self::maxMegabytes();

        return [
            "Hanya berkas .json (UTF-8). Bukan Word, Excel, CSV, PDF, atau zip.",
            "Ukuran paling besar {$max} MB.",
            "Akar berkas: objek dengan kunci items, atau array butir langsung.",
            "Setiap butir wajib: code, grade, dimension, stem_html, options.",
            "code berbentuk {$grades} lalu strip dan dua digit atau lebih (contoh X-01, XI-27, XII-40). Kode adalah identitas butir dan tidak boleh dipakai ulang (R7).",
            "grade harus X, XI, atau XII, dan sama dengan jenjang yang dipilih di formulir.",
            "dimension salah satu dari: {$dimensions}.",
            'stem_html dan body_html opsi boleh berisi HTML sederhana (paragraf, tabel, daftar). Bungkus tabel dengan overflow di sisi siswa sudah ditangani tampilan.',
            'Tepat lima opsi A–E. Tiap opsi: label, body_html. Tepat satu opsi is_key = true.',
            'Kode yang sudah ada di bank, atau satu kesalahan di berkas, menolak seluruh unggahan. Tidak ada impor sebagian.',
            'Kolom opsional: learning_objective, topic, semester, indicator, bloom_level, media_path, source, warnings. media_path hanya path di server; unggah ini tidak membawa gambar.',
            'Parameter IRT tidak ada di berkas ini. Centang parameter sementara, atau impor kalibrasi nanti lewat cat:import-parameters (R5).',
        ];
    }

    /**
     * Empat gerbang urut: berkas, identitas, opsi, akibat.
     *
     * @return list<array{title: string, line: string}>
     */
    public static function ruleSummaries(): array
    {
        $max = self::maxMegabytes();

        return [
            ['title' => 'Berkas', 'line' => "Hanya .json (UTF-8), paling besar {$max} MB."],
            ['title' => 'Identitas', 'line' => 'Tiap butir: code, grade, dimension, stem, lima opsi. Kode seperti X-01 tidak dipakai ulang.'],
            ['title' => 'Opsi', 'line' => 'Tepat lima opsi A–E dan tepat satu kunci.'],
            ['title' => 'Akibat', 'line' => 'Satu kesalahan atau kode yang sudah ada menolak seluruh berkas.'],
        ];
    }

    /**
     * @return array{dimensions: list<array{code: string, label: string}>, items: list<array<string, mixed>>}
     */
    public static function samplePayload(): array
    {
        return [
            'dimensions' => [
                ['code' => 'fluency', 'label' => 'Fluency (Kelancaran)'],
                ['code' => 'flexibility', 'label' => 'Flexibility (Keluwesan)'],
                ['code' => 'originality', 'label' => 'Originality (Orisinalitas)'],
                ['code' => 'elaboration', 'label' => 'Elaboration (Elaborasi)'],
                ['code' => 'solutif', 'label' => 'Solutif'],
                ['code' => 'adaptif', 'label' => 'Adaptif'],
                ['code' => 'prediktif', 'label' => 'Prediktif'],
            ],
            'items' => [
                self::sampleItem('X-91', 'fluency', 'A', 'Sebuah pasar tradisional sepi pembeli. Usulkan satu cara agar pedagang tetap berpenghasilan.'),
                self::sampleItem('X-92', 'solutif', 'C', 'Harga cabai naik mendadak. Pilih langkah yang paling masuk akal bagi pedagang kecil.'),
            ],
        ];
    }

    public static function sampleJson(): string
    {
        return json_encode(
            self::samplePayload(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n";
    }

    public static function shortHelper(): string
    {
        $max = self::maxMegabytes();

        return "Hanya .json (maks {$max} MB). Tiap butir: code, grade, dimension, stem_html, lima opsi A–E, tepat satu kunci. Kode yang sudah ada menolak seluruh berkas.";
    }

    /**
     * @return array<string, mixed>
     */
    private static function sampleItem(string $code, string $dimension, string $key, string $stem): array
    {
        $grade = explode('-', $code)[0];
        $bodies = [
            'A' => 'Menggelar promo paket hemat di hari pasar.',
            'B' => 'Menutup kios sampai harga kembali turun.',
            'C' => 'Mengganti dagangan sesuai permintaan pembeli minggu ini.',
            'D' => 'Menunggu bantuan tanpa mengubah cara berjualan.',
            'E' => 'Menaikkan harga semua barang secara merata.',
        ];

        return [
            'code' => $code,
            'grade' => $grade,
            'dimension' => $dimension,
            'topic' => 'Contoh unggahan',
            'stem_html' => '<p>'.$stem.'</p>',
            'source' => 'contoh-paket-soal.json',
            'options' => collect(['A', 'B', 'C', 'D', 'E'])->map(fn (string $label): array => [
                'label' => $label,
                'body_html' => '<p>'.$bodies[$label].'</p>',
                'is_key' => $label === $key,
            ])->all(),
        ];
    }
}
