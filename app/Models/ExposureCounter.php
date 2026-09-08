<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kunci primer gabungan (test_config_id, item_id) — tidak ada kolom id,
 * jadi model ini tidak boleh memakai increment bawaan Eloquent.
 */
class ExposureCounter extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'array';

    protected $fillable = [
        'test_config_id', 'item_id', 'times_selected', 'times_administered',
    ];

    protected function casts(): array
    {
        return [
            'times_selected' => 'integer',
            'times_administered' => 'integer',
        ];
    }

    public function testConfig(): BelongsTo
    {
        return $this->belongsTo(TestConfig::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
