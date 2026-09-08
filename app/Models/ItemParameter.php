<?php

declare(strict_types=1);

namespace App\Models;

use App\CAT\ItemParameter as CatItemParameter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemParameter extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'item_id', 'calibration_run_id', 'model', 'a', 'b', 'c',
        'se_a', 'se_b', 'infit', 'outfit', 'is_active', 'calibrated_at',
    ];

    protected function casts(): array
    {
        return [
            'a' => 'float',
            'b' => 'float',
            'c' => 'float',
            'se_a' => 'float',
            'se_b' => 'float',
            'infit' => 'float',
            'outfit' => 'float',
            'is_active' => 'boolean',
            'calibrated_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function calibrationRun(): BelongsTo
    {
        return $this->belongsTo(CalibrationRun::class);
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    /** Satu-satunya jembatan dari Eloquent ke mesin IRT murni di App\CAT. */
    public function toCat(): CatItemParameter
    {
        return new CatItemParameter($this->a, $this->b, $this->c);
    }
}
