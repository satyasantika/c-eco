<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationResult extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'simulation_run_id', 'replication', 'true_theta',
        'est_theta', 'se', 'n_items', 'items_json',
    ];

    protected function casts(): array
    {
        return [
            'replication' => 'integer',
            'true_theta' => 'float',
            'est_theta' => 'float',
            'se' => 'float',
            'n_items' => 'integer',
            'items_json' => 'array',
        ];
    }

    public function simulationRun(): BelongsTo
    {
        return $this->belongsTo(SimulationRun::class);
    }
}
