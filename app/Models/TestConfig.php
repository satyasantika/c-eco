<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestConfig extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'item_bank_id', 'name', 'mode', 'min_items', 'max_items', 'se_target',
        'theta_prior_mean', 'theta_prior_sd', 'selection_method',
        'exposure_method', 'exposure_k', 'content_balancing_json',
        'shuffle_options', 'is_active',
        'grade_share_x', 'grade_share_xi', 'grade_share_xii', 'pool_size',
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
            'grade_share_x' => 'integer',
            'grade_share_xi' => 'integer',
            'grade_share_xii' => 'integer',
            'pool_size' => 'integer',
        ];
    }

    public function itemBank(): BelongsTo
    {
        return $this->belongsTo(ItemBank::class);
    }

    /**
     * Butir yang dirakit ke paket ini. Kosong = seluruh bank jenjang.
     */
    public function packageItems(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'test_config_items');
    }

    public function examGroups(): HasMany
    {
        return $this->hasMany(ExamGroup::class);
    }

    public function usesExplicitPool(): bool
    {
        return $this->packageItems()->exists();
    }

    /** Paket campuran yang dihitung dari persen jenjang, bukan seluruh bank. */
    public function usesGradeShares(): bool
    {
        $sum = (int) $this->grade_share_x + (int) $this->grade_share_xi + (int) $this->grade_share_xii;

        return $sum === 100 && (int) $this->pool_size > 0;
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
