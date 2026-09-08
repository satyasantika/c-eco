<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemOption;
use App\Services\ItemBankImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ItemBankImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_whole_bank_with_one_key_per_item(): void
    {
        ItemBankImporter::fromDataDirectory()->import();

        $this->assertSame(120, Item::query()->count());
        $this->assertSame(600, ItemOption::query()->count());
        $this->assertSame(120, ItemOption::query()->where('is_key', true)->count());

        foreach (['X', 'XI', 'XII'] as $grade) {
            $this->assertSame(40, Item::query()->whereRelation('itemBank', 'grade', $grade)->count());
        }
    }

    /** SPEC §10 uji #9: edit manual lewat Filament tidak boleh hilang. */
    public function test_reseeding_does_not_overwrite_manual_edits(): void
    {
        $importer = ItemBankImporter::fromDataDirectory();
        $importer->import();

        Item::query()->where('code', 'X-01')->update(['stem_html' => '<p>diperbaiki manual</p>']);

        $counts = $importer->import();

        $this->assertSame('<p>diperbaiki manual</p>', Item::query()->where('code', 'X-01')->value('stem_html'));
        $this->assertSame(0, $counts['items']);
        $this->assertSame(120, $counts['skipped']);
        $this->assertSame(120, Item::query()->count());
        $this->assertSame(600, ItemOption::query()->count());
    }

    public function test_force_restores_the_json_content(): void
    {
        $importer = ItemBankImporter::fromDataDirectory();
        $importer->import();

        Item::query()->where('code', 'X-01')->update(['stem_html' => '<p>diperbaiki manual</p>']);
        $importer->import(force: true);

        $this->assertStringNotContainsString(
            'diperbaiki manual',
            (string) Item::query()->where('code', 'X-01')->value('stem_html')
        );
        $this->assertSame(120, Item::query()->count());
    }

    public function test_it_refuses_a_bank_whose_item_has_no_key(): void
    {
        $directory = $this->makeBankWithoutKey();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('0 opsi bertanda kunci');

        ItemBankImporter::fromDataDirectory($directory)->import();
    }

    private function makeBankWithoutKey(): string
    {
        $directory = sys_get_temp_dir().'/ceco-bank-'.uniqid();
        mkdir($directory);

        $payload = json_decode(
            (string) file_get_contents(base_path('data/items-all.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $payload['items'] = [$payload['items'][0]];

        foreach ($payload['items'][0]['options'] as $i => $option) {
            $payload['items'][0]['options'][$i]['is_key'] = false;
        }

        file_put_contents($directory.'/items-all.json', json_encode($payload, JSON_THROW_ON_ERROR));

        return $directory;
    }
}
