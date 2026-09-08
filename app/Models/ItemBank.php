<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ItemBank extends Model
{
    protected $fillable = [
        'grade', 'version', 'irt_model', 'scale_note',
        'calibration_n', 'calibration_source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'calibration_n' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function calibrationRuns(): HasMany
    {
        return $this->hasMany(CalibrationRun::class);
    }

    public function testConfigs(): HasMany
    {
        return $this->hasMany(TestConfig::class);
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }

    public function label(): string
    {
        return "{$this->grade} · {$this->version}";
    }
}
