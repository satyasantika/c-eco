<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Participant extends Model
{
    protected $fillable = [
        'school_id', 'class_name', 'student_code', 'display_name', 'sex', 'consent_at',
    ];

    protected function casts(): array
    {
        return ['consent_at' => 'datetime'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function testSessions(): HasMany
    {
        return $this->hasMany(TestSession::class);
    }
}
