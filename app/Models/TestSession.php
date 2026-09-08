<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TestSession extends Model
{
    protected $fillable = [
        'participant_id', 'test_config_id', 'item_bank_id', 'access_token',
        'rng_seed', 'device_uuid', 'user_agent_hash', 'ip_hash', 'status',
        'theta', 'se', 'items_administered', 'started_at', 'finished_at',
        'last_seen_at', 'effective_connection',
    ];

    protected function casts(): array
    {
        return [
            'rng_seed' => 'integer',
            'theta' => 'float',
            'se' => 'float',
            'items_administered' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'access_token';
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function testConfig(): BelongsTo
    {
        return $this->belongsTo(TestConfig::class);
    }

    public function itemBank(): BelongsTo
    {
        return $this->belongsTo(ItemBank::class);
    }

    public function sessionItems(): HasMany
    {
        return $this->hasMany(SessionItem::class)->orderBy('sequence');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SessionEvent::class);
    }
}
