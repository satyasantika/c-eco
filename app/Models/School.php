<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'city', 'exam_simulation_id'];

    public function examSimulation(): BelongsTo
    {
        return $this->belongsTo(ExamSimulation::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function examGroups(): HasMany
    {
        return $this->hasMany(ExamGroup::class);
    }
}
