<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SeedProvisionalParametersCommand;
use App\Models\CalibrationRun;
use App\Models\Item;
use App\Models\ItemParameter;
use App\Services\ItemBankImporter;
use App\Services\ItemParameterImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ItemParameterImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ItemBankImporter::fromDataDirectory()->import();
    }

    public function test_provisional_command_gives_every_item_exactly_one_active_parameter(): void
    {
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();

        $this->assertSame(0, Item::query()
            ->whereDoesntHave('parameters', fn ($q) => $q->where('is_active', true))
            ->count());

        $this->assertSame(120, ItemParameter::query()->where('is_active', true)->count());
        $this->assertTrue(CalibrationRun::query()->where('is_provisional', true)->exists());
    }

    public function test_provisional_parameters_stay_inside_their_declared_ranges(): void
    {
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();

        $parameters = ItemParameter::query()->where('is_active', true)->get();

        $this->assertGreaterThanOrEqual(0.8, $parameters->min('a'));
        $this->assertLessThanOrEqual(1.6, $parameters->max('a'));
        $this->assertGreaterThanOrEqual(-2.5, $parameters->min('b'));
        $this->assertLessThanOrEqual(2.5, $parameters->max('b'));
        $this->assertSame([0.2], $parameters->pluck('c')->unique()->values()->all());
    }

    public function test_the_fixed_seed_makes_provisional_parameters_reproducible(): void
    {
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();
        $first = $this->activeParametersByCode();

        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();
        $second = $this->activeParametersByCode();

        $this->assertSame($first, $second);
        $this->assertSame(20260921, SeedProvisionalParametersCommand::SEED);
    }

    /** R5: kalibrasi ulang menambah versi, tidak menimpa yang lama. */
    public function test_reimport_versions_parameters_instead_of_updating_them(): void
    {
        $this->artisan('cat:seed-provisional-parameters')->assertSuccessful();
        $original = ItemParameter::query()->orderBy('id')->first();

        $this->artisan('cat:import-parameters', ['file' => $this->csv([
            ['X-01', '1.1000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
        ])])->assertSuccessful();

        $original->refresh();

        $this->assertFalse($original->is_active);
        $this->assertSame(1.1, ItemParameter::query()
            ->whereRelation('item', 'code', 'X-01')
            ->where('is_active', true)
            ->value('a'));
        $this->assertSame(2, ItemParameter::query()->whereRelation('item', 'code', 'X-01')->count());
    }

    public function test_a_single_out_of_range_row_rejects_the_whole_file(): void
    {
        $file = $this->csv([
            ['X-01', '1.1000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
            ['X-02', '4.2000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
            ['X-03', '1.0000', '9.9000', '0.2000', '', '', '3PL-c-fixed', '', ''],
        ]);

        $this->artisan('cat:import-parameters', ['file' => $file])->assertFailed();

        $this->assertSame(0, ItemParameter::query()->count());
        $this->assertSame(0, CalibrationRun::query()->count());
    }

    public function test_it_reports_every_bad_row_rather_than_only_the_first(): void
    {
        $importer = new ItemParameterImporter;

        $file = $this->csv([
            ['X-01', '4.2000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
            ['X-02', '1.0000', '0.5000', '0.9000', '', '', '3PL-c-fixed', '', ''],
        ]);

        try {
            $importer->parseCsv($file);
            $this->fail('CSV bermasalah seharusnya ditolak.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('baris 2: a', $e->getMessage());
            $this->assertStringContainsString('baris 3: c', $e->getMessage());
        }
    }

    public function test_an_unknown_item_code_rejects_the_file(): void
    {
        $file = $this->csv([
            ['X-01', '1.1000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
            ['ZZ-99', '1.1000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
        ]);

        $this->artisan('cat:import-parameters', ['file' => $file])->assertFailed();

        $this->assertSame(0, ItemParameter::query()->count());
    }

    public function test_a_duplicated_item_code_rejects_the_file(): void
    {
        $file = $this->csv([
            ['X-01', '1.1000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
            ['X-01', '1.2000', '0.5000', '0.2000', '', '', '3PL-c-fixed', '', ''],
        ]);

        $this->artisan('cat:import-parameters', ['file' => $file])->assertFailed();

        $this->assertSame(0, ItemParameter::query()->count());
    }

    /**
     * @return array<string, array{a: float, b: float}>
     */
    private function activeParametersByCode(): array
    {
        return ItemParameter::query()
            ->where('is_active', true)
            ->with('item:id,code')
            ->get()
            ->mapWithKeys(fn (ItemParameter $p): array => [
                $p->item->code => ['a' => $p->a, 'b' => $p->b],
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ceco-params').'.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, ItemParameterImporter::HEADER);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
