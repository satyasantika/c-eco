<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\CAT\EapEstimator;
use App\CAT\ItemParameter;
use App\CAT\Response;
use PHPUnit\Framework\TestCase;

/**
 * SPEC §10 uji #1 — golden test.
 *
 * Fixture-nya dibangkitkan di luar PHP, jadi kesalahan tafsir rumus di
 * App\CAT tidak bisa lolos hanya karena kode dan ujinya sepakat.
 */
class CatGoldenTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../Fixtures/golden_eap.json';

    public function test_the_estimator_reproduces_every_golden_case(): void
    {
        $fixture = $this->fixture();
        $estimator = new EapEstimator;
        $tolerance = (float) $fixture['tolerance'];

        foreach ($fixture['cases'] as $index => $case) {
            $responses = [];

            foreach ($case['items'] as $position => $item) {
                $responses[] = new Response(
                    new ItemParameter((float) $item['a'], (float) $item['b'], (float) $item['c']),
                    $case['responses'][$position] === 1,
                );
            }

            $estimate = $estimator->estimate($responses);

            $this->assertEqualsWithDelta($case['theta'], $estimate->theta, $tolerance, "kasus #{$index}: theta");
            $this->assertEqualsWithDelta($case['se'], $estimate->se, $tolerance, "kasus #{$index}: SE");
        }

        $this->assertCount(1000, $fixture['cases']);
    }

    /**
     * Fixture yang masih dari pembangkit Python belum membuktikan kesetaraan
     * dengan catR. Uji ini menandainya sebagai pekerjaan tersisa, dan akan
     * berhenti mengeluh begitu tools/generate_golden.R dijalankan.
     */
    public function test_it_says_plainly_when_the_fixture_did_not_come_from_cat_r(): void
    {
        $generator = $this->fixture()['generator'];

        if ($generator !== 'catR') {
            $this->markTestIncomplete(
                "Fixture golden dibangkitkan oleh '{$generator}', bukan catR. ".
                'Bukti kesetaraan untuk artikel dan HKI belum ada — jalankan '.
                'tools/generate_golden.R di mesin ber-R lalu jalankan ulang uji ini.'
            );
        }

        $this->assertSame('catR', $generator);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(): array
    {
        if (! is_file(self::FIXTURE)) {
            $this->markTestSkipped(
                'tests/Fixtures/golden_eap.json belum ada. Jalankan tools/generate_golden.R '.
                '(atau tools/generate_golden.py untuk pemeriksaan sementara).'
            );
        }

        return json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
    }
}
