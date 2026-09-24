<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu izin pindah HP dari pengawas. previous_token adalah kunci HP lama;
 * HP itu ditolak selama izin berlaku, dan kuncinya dipulihkan bila izin
 * kedaluwarsa tanpa dipakai.
 */
class SeatRelease extends Model
{
    protected $fillable = [
        'test_session_id', 'released_by', 'previous_token', 'reason',
        'expires_at', 'used_at', 'restored_at',
    ];

    protected $hidden = ['previous_token'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'restored_at' => 'datetime',
        ];
    }

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    /** Belum dipakai HP baru dan belum dipulihkan (bisa sudah kedaluwarsa). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('used_at')->whereNull('restored_at');
    }

    public function isExpired(?Carbon $now = null): bool
    {
        return $this->expires_at->lte($now ?? Carbon::now());
    }
}
