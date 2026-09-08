<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Item extends Model
{
    protected $fillable = [
        'item_bank_id', 'code', 'dimension_id', 'learning_objective', 'topic',
        'semester', 'indicator', 'bloom_level', 'stem_html', 'media_path',
        'status', 'source_note',
    ];

    public function itemBank(): BelongsTo
    {
        return $this->belongsTo(ItemBank::class);
    }

    public function dimension(): BelongsTo
    {
        return $this->belongsTo(Dimension::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ItemOption::class)->orderBy('display_order');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(ItemParameter::class);
    }

    public function activeParameter(): HasOne
    {
        return $this->hasOne(ItemParameter::class)->where('is_active', true);
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class);
    }

    public function exposureCounters(): HasMany
    {
        return $this->hasMany(ExposureCounter::class);
    }

    public function testConfigs(): BelongsToMany
    {
        return $this->belongsToMany(TestConfig::class, 'test_config_items');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
