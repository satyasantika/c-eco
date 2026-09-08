<?php

declare(strict_types=1);

namespace App\Services;

use App\CAT\Candidate;
use App\CAT\ContentBalancer;
use App\CAT\LinearSelector;
use App\CAT\NoCandidateException;
use App\Models\Item;
use App\Models\TestConfig;
use Illuminate\Support\Collection;

/**
 * Menyusun bentuk linear tetap untuk sebuah test_config.
 *
 * Dipakai untuk mencetak bentuk kertas (RUNBOOK lapis L3). Butir dan urutannya
 * dihitung dengan LinearSelector yang sama dengan yang dipakai sesi sungguhan,
 * bukan dengan "dua puluh butir pertama menurut kode". Kalau lembar kertas
 * berisi butir yang berbeda dari mode linear di layar, dua kelompok siswa
 * mengerjakan tes yang berbeda dan datanya tidak bisa disatukan.
 *
 * Pemilihan linear tidak bergantung pada jawaban peserta, jadi bentuk ini
 * benar-benar deterministik dan bisa dihitung ulang kapan saja.
 */
class LinearFormBuilder
{
    /**
     * @return Collection<int, Item> berurutan sesuai posisi di bentuk tes
     */
    public function build(TestConfig $config): Collection
    {
        $items = $this->candidateItems($config);
        $selector = new LinearSelector($this->balancer($config), $config->max_items);

        $candidates = $items
            ->map(fn (Item $item): Candidate => new Candidate(
                itemId: $item->id,
                dimension: $item->dimension->code,
                parameter: $item->activeParameter->toCat(),
            ))
            ->values()
            ->all();

        $form = [];
        $dimensions = [];

        for ($sequence = 1; $sequence <= $config->max_items; $sequence++) {
            try {
                $result = $selector->select($candidates, $dimensions, $sequence, 0.0);
            } catch (NoCandidateException) {
                break;
            }

            $chosen = $result->candidate;
            $form[] = $items[$chosen->itemId];
            $dimensions[] = $chosen->dimension;

            $candidates = array_values(array_filter(
                $candidates,
                static fn (Candidate $c): bool => $c->itemId !== $chosen->itemId
            ));
        }

        return new Collection($form);
    }

    /**
     * @return Collection<int, Item> berkunci item_id
     */
    private function candidateItems(TestConfig $config): Collection
    {
        return Item::query()
            ->active()
            ->where('item_bank_id', $config->item_bank_id)
            ->whereHas('parameters', fn ($q) => $q->where('is_active', true))
            ->with(['activeParameter', 'dimension', 'options'])
            ->orderBy('id')
            ->get()
            ->keyBy('id');
    }

    private function balancer(TestConfig $config): ContentBalancer
    {
        $counts = Item::query()
            ->active()
            ->where('item_bank_id', $config->item_bank_id)
            ->join('dimensions', 'items.dimension_id', '=', 'dimensions.id')
            ->selectRaw('dimensions.code as code, count(*) as total')
            ->groupBy('dimensions.code')
            ->pluck('total', 'code')
            ->map(static fn ($n): int => (int) $n)
            ->all();

        return ContentBalancer::fromCounts($counts);
    }
}
