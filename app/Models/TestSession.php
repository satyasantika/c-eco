<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class TestSession extends Model
{
    protected $fillable = [
        'participant_id', 'test_config_id', 'item_bank_id', 'exam_group_id',
        'access_token', 'rng_seed', 'device_uuid', 'user_agent_hash', 'ip_hash',
        'status', 'claimed_at', 'opened_at', 'theta', 'se', 'items_administered',
        'started_at', 'finished_at', 'last_seen_at', 'effective_connection',
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
            'claimed_at' => 'datetime',
            'opened_at' => 'datetime',
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

    public function examGroup(): BelongsTo
    {
        return $this->belongsTo(ExamGroup::class);
    }

    /** Kursi rombongan yang belum diisi identitas setelah scan QR. */
    public function isUnclaimed(): bool
    {
        return $this->exam_group_id !== null && $this->claimed_at === null;
    }

    /**
     * Slip kertas (tanpa rombongan) selalu terbuka. Kursi QR menunggu starts_at.
     */
    public function examWindowOpen(?Carbon $now = null): bool
    {
        if ($this->exam_group_id === null) {
            return true;
        }

        $group = $this->examGroup;

        return $group === null || $group->hasStarted($now);
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
