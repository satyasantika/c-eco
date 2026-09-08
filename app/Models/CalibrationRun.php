<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalibrationRun extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'item_bank_id', 'software', 'version', 'sample_n',
        'method', 'is_provisional', 'notes', 'run_at',
    ];

    protected function casts(): array
    {
        return [
            'sample_n' => 'integer',
            'is_provisional' => 'boolean',
            'run_at' => 'datetime',
        ];
    }

    public function itemBank(): BelongsTo
    {
        return $this->belongsTo(ItemBank::class);
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ItemParameter::class);
    }
}
