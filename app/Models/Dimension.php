<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dimension extends Model
{
    public $timestamps = false;

    protected $fillable = ['code', 'name', 'description', 'display_order'];

    protected function casts(): array
    {
        return ['display_order' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }
}
