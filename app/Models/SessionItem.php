<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'test_session_id', 'sequence', 'item_id', 'item_parameter_id',
        'theta_before', 'se_before', 'information_at_selection', 'selection_rule',
        'candidate_pool_json', 'option_permutation_json', 'response_label',
        'is_correct', 'theta_after', 'se_after', 'shown_at', 'answered_at',
        'latency_ms', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'theta_before' => 'float',
            'se_before' => 'float',
            'information_at_selection' => 'float',
            'candidate_pool_json' => 'array',
            'option_permutation_json' => 'array',
            'is_correct' => 'boolean',
            'theta_after' => 'float',
            'se_after' => 'float',
            'shown_at' => 'datetime',
            'answered_at' => 'datetime',
            'latency_ms' => 'integer',
            'retry_count' => 'integer',
        ];
    }

    public function testSession(): BelongsTo
    {
        return $this->belongsTo(TestSession::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function itemParameter(): BelongsTo
    {
        return $this->belongsTo(ItemParameter::class);
    }
}
