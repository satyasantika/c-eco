<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Pages\UploadItems;
use App\Filament\Resources\ItemBanks\ItemBankResource;
use App\Filament\Resources\ItemBanks\Pages\ListItemBanks;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Items\Pages\ListItems;
use App\Models\Item;
use App\Models\ItemBank;
use App\Models\ItemOption;
use App\Models\ItemParameter;
use App\Models\TestConfig;
use App\Models\User;
use App\Services\ItemBankImporter;
use App\Services\ItemPackageImporter;
use App\Support\ItemPackageFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ItemPackageImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_a_new_package_with_provisional_parameters_and_a_config(): void
    {
        $file = $this->jsonFile($this->payload([
            $this->item('X-41', 'fluency', 'A'),
            $this->item('X-42', 'flexibility', 'B'),
        ]));

        $this->artisan('cat:import-item-package', [
            'file' => $file,
            '--grade' => 'X',
            '--package-version' => '2026.2',
        ])->assertSuccessful();

        $bank = ItemBank::query()->where('grade', 'X')->where('version', '2026.2')->firstOrFail();

        $this->assertSame(2, Item::query()->where('item_bank_id', $bank->id)->count());
        $this->assertSame(10, ItemOption::query()->whereIn('item_id', $bank->items()->pluck('id'))->count());
        $this->assertSame(2, ItemOption::query()->where('is_key', true)->count());
        $this->assertSame(2, ItemParameter::query()->where('is_active', true)->count());
        $this->assertTrue(TestConfig::query()->where('item_bank_id', $bank->id)->where('is_active', true)->exists());
        $this->assertSame('Adaptif X 2026.2', TestConfig::query()->where('item_bank_id', $bank->id)->value('name'));
    }

    public function test_duplicate_codes_reject_the_whole_file_and_leave_the_original_bank_untouched(): void
    {
        ItemBankImporter::fromDataDirectory()->import();
        $before = Item::query()->count();

        $file = $this->jsonFile($this->payload([
            $this->item('X-01', 'fluency', 'A'),
            $this->item('X-41', 'flexibility', 'B'),
        ]));

        $this->artisan('cat:import-item-package', [
            'file' => $file,
            '--grade' => 'X',
            '--package-version' => '2026.2',
        ])->assertFailed();

        $this->assertSame($before, Item::query()->count());
        $this->assertFalse(ItemBank::query()->where('version', '2026.2')->exists());
        $this->assertFalse(Item::query()->where('code', 'X-41')->exists());
    }

    public function test_a_missing_key_rejects_the_file(): void
    {
        $item = $this->item('X-41', 'fluency', 'A');
        foreach ($item['options'] as $i => $option) {
            $item['options'][$i]['is_key'] = false;
        }

        try {
            app(ItemPackageImporter::class)->importFromFile(
                $this->jsonFile($this->payload([$item])),
                'X',
                '2026.2',
            );
            $this->fail('Paket tanpa kunci seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('0 opsi bertanda kunci', $e->getMessage());
        }

        $this->assertSame(0, Item::query()->count());
    }

    public function test_a_grade_mismatch_rejects_the_file(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('jenjang XI tidak cocok');

        app(ItemPackageImporter::class)->importFromFile(
            $this->jsonFile($this->payload([$this->item('XI-41', 'fluency', 'A')])),
            'X',
            '2026.2',
        );
    }

    public function test_new_codes_can_be_appended_to_an_existing_package(): void
    {
        $importer = app(ItemPackageImporter::class);
        $importer->importFromFile(
            $this->jsonFile($this->payload([$this->item('X-41', 'fluency', 'A')])),
            'X',
            '2026.2',
        );

        $importer->importFromFile(
            $this->jsonFile($this->payload([$this->item('X-42', 'flexibility', 'B')])),
            'X',
            '2026.2',
        );

        $bank = ItemBank::query()->where('grade', 'X')->where('version', '2026.2')->firstOrFail();
        $this->assertSame(2, $bank->items()->count());
        $this->assertSame(1, ItemBank::query()->where('grade', 'X')->where('version', '2026.2')->count());
    }

    public function test_a_bare_item_array_is_accepted(): void
    {
        $file = $this->jsonFile([$this->item('XII-41', 'solutif', 'C')]);

        $this->artisan('cat:import-item-package', [
            'file' => $file,
            '--grade' => 'XII',
            '--package-version' => 'uji',
            '--no-provisional' => true,
        ])->assertSuccessful();

        $this->assertTrue(Item::query()->where('code', 'XII-41')->exists());
        $this->assertSame(0, ItemParameter::query()->count());
    }

    public function test_the_admin_sees_the_import_action_and_the_package_pages_render(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->get('/admin/item-banks')->assertOk();
        $this->actingAs($admin)->get('/admin/items')->assertOk();
        $this->actingAs($admin)
            ->get('/admin/unggah-soal')
            ->assertOk()
            ->assertSee('Berkas yang boleh diunggah')
            ->assertSee('.json')
            ->assertSee('X-91')
            ->assertSee('.docx');

        Livewire::actingAs($admin)
            ->test(ListItems::class)
            ->assertActionVisible('importPackage');

        Livewire::actingAs($admin)
            ->test(ListItemBanks::class)
            ->assertActionVisible('importPackage');
    }

    public function test_admin_can_import_a_package_from_the_panel(): void
    {
        $admin = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'paket.json',
            json_encode($this->payload([$this->item('XI-41', 'adaptif', 'D')]), JSON_THROW_ON_ERROR),
        );

        Livewire::actingAs($admin)
            ->test(ListItemBanks::class)
            ->callAction('importPackage', [
                'grade' => 'XI',
                'version' => '2026.2',
                'json' => $file,
                'provisional' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(Item::query()->where('code', 'XI-41')->exists());
        $this->assertTrue(ItemParameter::query()->where('is_active', true)->exists());
    }

    public function test_json_sniffed_as_html_because_stems_are_dense_still_imports(): void
    {
        $admin = User::factory()->create();
        $json = $this->htmlDensePackageJson('XII-91');
        $this->assertSame('text/html', (new \finfo(FILEINFO_MIME_TYPE))->buffer(substr($json, 0, 64 * 1024)));

        $file = UploadedFile::fake()
            ->createWithContent('items-XII.json', $json)
            ->mimeType('text/html');

        Livewire::actingAs($admin)
            ->test(ListItemBanks::class)
            ->callAction('importPackage', [
                'grade' => 'XII',
                'version' => '2026.1',
                'json' => $file,
                'provisional' => true,
            ])
            ->assertHasNoActionErrors();

        $this->assertTrue(Item::query()->where('code', 'XII-91')->exists());
    }

    public function test_an_html_file_is_rejected_even_when_the_mime_is_html(): void
    {
        $admin = User::factory()->create();
        $file = UploadedFile::fake()
            ->createWithContent('soal.html', '<html><p>bukan json</p></html>')
            ->mimeType('text/html');

        Livewire::actingAs($admin)
            ->test(ListItemBanks::class)
            ->callAction('importPackage', [
                'grade' => 'XII',
                'version' => '2026.1',
                'json' => $file,
                'provisional' => true,
            ])
            ->assertHasActionErrors(['json']);

        $this->assertSame(0, Item::query()->count());
    }

    public function test_a_peneliti_can_read_packages_but_cannot_import(): void
    {
        $peneliti = User::factory()->peneliti()->create();

        $this->actingAs($peneliti)->get('/admin/item-banks')->assertOk();
        $this->assertFalse(ItemBankResource::canEdit(new ItemBank));
        $this->assertFalse(ItemBankResource::canCreate());
        $this->assertFalse(ItemResource::canCreate());

        Livewire::actingAs($peneliti)
            ->test(ListItems::class)
            ->assertActionHidden('importPackage');

        $this->actingAs($peneliti)->get('/admin/unggah-soal')->assertForbidden();
        $this->actingAs(User::factory()->operator()->create())->get('/admin/unggah-soal')->assertForbidden();
    }

    public function test_the_published_example_json_imports_cleanly(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ceco-contoh').'.json';
        file_put_contents($path, ItemPackageFormat::sampleJson());

        app(ItemPackageImporter::class)->importFromFile($path, 'X', 'contoh');

        $this->assertTrue(Item::query()->where('code', 'X-91')->exists());
        $this->assertTrue(Item::query()->where('code', 'X-92')->exists());
        $this->assertSame(1, Item::query()->where('code', 'X-91')->firstOrFail()->options()->where('is_key', true)->count());
    }

    public function test_admin_can_download_the_example_json(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(UploadItems::class)
            ->callAction('downloadExample')
            ->assertFileDownloaded(ItemPackageFormat::EXAMPLE_FILENAME);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{dimensions: list<array<string, string>>, items: list<array<string, mixed>>}
     */
    private function payload(array $items): array
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
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function item(string $code, string $dimension, string $key): array
    {
        $grade = explode('-', $code)[0];

        return [
            'code' => $code,
            'grade' => $grade,
            'dimension' => $dimension,
            'stem_html' => "<p>Stem {$code}</p>",
            'options' => collect(['A', 'B', 'C', 'D', 'E'])->map(fn (string $label): array => [
                'label' => $label,
                'body_html' => "<p>Opsi {$label}</p>",
                'is_key' => $label === $key,
            ])->all(),
        ];
    }

    private function jsonFile(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ceco-paket').'.json';
        file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));

        return $path;
    }

    /** JSON sah yang 64 KB pertamanya dicium libmagic sebagai text/html, seperti items-XII.json. */
    private function htmlDensePackageJson(string $code): string
    {
        $chunk = '<table><tr><td><p>pasar</p></td><td><p>harga</p></td></tr></table>';
        $item = $this->item($code, 'originality', 'A');
        $item['stem_html'] = '<p>Soal padat HTML.</p>'.str_repeat($chunk, 900);
        $json = json_encode($this->payload([$item]), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->assertGreaterThan(64 * 1024, strlen($json));

        return $json;
    }
}
