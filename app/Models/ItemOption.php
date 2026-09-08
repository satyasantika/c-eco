<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemOption extends Model
{
    public $timestamps = false;

    protected $fillable = ['item_id', 'label', 'body_html', 'is_key', 'display_order'];

    protected function casts(): array
    {
        return [
            'is_key' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
