<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\TestConfig;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kolam butir sebuah test_config.
 *
 * Kosongkan pivot test_config_items untuk memakai seluruh bank jenjang
 * (perilaku lama). Isi pivot untuk merakit paket campuran dari X, XI, XII
 * tanpa menyalin butir — kode tetap identitas unik (R7).
 */
class ItemPool
{
    /**
     * @return Builder<Item>
     */
    public function query(TestConfig $config): Builder
    {
        $query = Item::query()
            ->active()
            ->whereHas('parameters', fn ($q) => $q->where('is_active', true));

        $ids = $config->packageItems()->pluck('id');

        if ($ids->isNotEmpty()) {
            return $query->whereIn('items.id', $ids);
        }

        return $query->where('item_bank_id', $config->item_bank_id);
    }
}
