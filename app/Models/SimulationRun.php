<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimulationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_config_id', 'n_replications', 'theta_distribution',
        'seed', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'n_replications' => 'integer',
            'seed' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function testConfig(): BelongsTo
    {
        return $this->belongsTo(TestConfig::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(SimulationResult::class);
    }
}
