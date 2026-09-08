<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestConfig extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'item_bank_id', 'name', 'mode', 'min_items', 'max_items', 'se_target',
        'theta_prior_mean', 'theta_prior_sd', 'selection_method',
        'exposure_method', 'exposure_k', 'content_balancing_json',
        'shuffle_options', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_items' => 'integer',
            'max_items' => 'integer',
            'se_target' => 'float',
            'theta_prior_mean' => 'float',
            'theta_prior_sd' => 'float',
            'exposure_k' => 'integer',
            'content_balancing_json' => 'array',
            'shuffle_options' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function itemBank(): BelongsTo
    {
        return $this->belongsTo(ItemBank::class);
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function exposureCounters(): HasMany
    {
        return $this->hasMany(ExposureCounter::class);
    }

    public function simulationRuns(): HasMany
    {
        return $this->hasMany(SimulationRun::class);
    }
}
