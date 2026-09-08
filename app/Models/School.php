<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class School extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'city'];

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }
}
