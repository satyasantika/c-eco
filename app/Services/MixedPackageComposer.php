<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\TestConfig;
use RuntimeException;

/**
 * Merakit kolam butir campuran dari persen jenjang X / XI / XII.
 *
 * Butir tidak disalin; yang disimpan hanya id di test_config_items (R7).
 */
class MixedPackageComposer
{
    /**
     * @return array{X: int, XI: int, XII: int}
     */
    public static function allocate(int $pool, int $shareX, int $shareXi, int $shareXii): array
    {
        if ($pool < 1) {
            throw new RuntimeException('Jumlah butir paket minimal 1.');
        }

        if ($shareX + $shareXi + $shareXii !== 100) {
            throw new RuntimeException('Jumlah persen X + XI + XII harus 100.');
        }

        $raw = [
            'X' => $pool * $shareX / 100,
            'XI' => $pool * $shareXi / 100,
            'XII' => $pool * $shareXii / 100,
        ];
        $counts = [
            'X' => (int) floor($raw['X']),
            'XI' => (int) floor($raw['XI']),
            'XII' => (int) floor($raw['XII']),
        ];
        $remain = $pool - array_sum($counts);
        $order = ['X', 'XI', 'XII'];
        usort($order, function (string $a, string $b) use ($raw, $counts): int {
            $fa = $raw[$a] - $counts[$a];
            $fb = $raw[$b] - $counts[$b];
            if ($fa === $fb) {
                return strcmp($a, $b);
            }

            return $fb <=> $fa;
        });

        $i = 0;
        while ($remain > 0) {
            $counts[$order[$i % 3]]++;
            $remain--;
            $i++;
        }

        return $counts;
    }

    public static function preview(?int $shareX, ?int $shareXi, ?int $shareXii, ?int $pool): string
    {
        $x = (int) $shareX;
        $xi = (int) $shareXi;
        $xii = (int) $shareXii;
        $sum = $x + $xi + $xii;

        if ($sum === 0) {
            return 'Kosongkan persen (semua 0) untuk memakai seluruh bank acuan. Isi sampai berjumlah 100 untuk paket campuran.';
        }

        if ($sum !== 100) {
            return "Jumlah sekarang {$sum}%. Sesuaikan sampai 100.";
        }

        if ($pool === null || $pool < 1) {
            return 'Persen sudah 100. Isi jumlah butir paket untuk melihat hitungan per jenjang.';
        }

        $counts = self::allocate($pool, $x, $xi, $xii);

        return "Hitungan: {$counts['X']} butir X, {$counts['XI']} XI, {$counts['XII']} XII.";
    }

    public function apply(TestConfig $config): void
    {
        if (! $config->usesGradeShares()) {
            return;
        }

        if ($config->testSessions()->whereIn('status', ['in_progress', 'completed'])->exists()) {
            throw new RuntimeException('Paket ini sudah dipakai sesi berjalan. Buat paket baru, jangan ubah komposisi.');
        }

        $pool = (int) $config->pool_size;
        $max = (int) $config->max_items;

        if ($pool < $max) {
            throw new RuntimeException("Jumlah butir paket ({$pool}) tidak boleh lebih kecil dari maksimal butir tes ({$max}).");
        }

        $counts = self::allocate(
            $pool,
            (int) $config->grade_share_x,
            (int) $config->grade_share_xi,
            (int) $config->grade_share_xii,
        );

        $ids = [];
        foreach ($counts as $grade => $n) {
            if ($n === 0) {
                continue;
            }
            $ids = array_merge($ids, $this->pickIds($grade, $n));
        }

        $config->packageItems()->sync($ids);
    }

    /**
     * @return list<int>
     */
    public function pickIds(string $grade, int $n): array
    {
        $items = Item::query()
            ->active()
            ->whereHas('itemBank', fn ($q) => $q->where('grade', $grade)->where('is_active', true))
            ->whereHas('parameters', fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->get(['id', 'dimension_id', 'code']);

        if ($items->count() < $n) {
            throw new RuntimeException(
                "Jenjang {$grade} hanya punya {$items->count()} butir siap tes, diminta {$n}."
            );
        }

        $queues = $items->groupBy(fn (Item $item): string => (string) $item->dimension_id)->map->values();
        $cursors = [];
        foreach ($queues->keys() as $key) {
            $cursors[(string) $key] = 0;
        }

        $picked = [];
        while (count($picked) < $n) {
            $added = false;
            foreach ($queues as $key => $list) {
                if (count($picked) >= $n) {
                    break;
                }
                $i = $cursors[(string) $key];
                if ($i < $list->count()) {
                    $picked[] = (int) $list[$i]->id;
                    $cursors[(string) $key]++;
                    $added = true;
                }
            }
            if (! $added) {
                break;
            }
        }

        return $picked;
    }
};
