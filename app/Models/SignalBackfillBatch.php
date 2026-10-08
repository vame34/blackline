<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalBackfillBatch extends Model
{
    protected $fillable = [
        'exchange',
        'provider',
        'interval',
        'context_interval',
        'limit',
        'context_limit',
        'start_time_ms',
        'end_time_ms',
        'symbols',
        'fallbacks',
        'category_slug',
        'created_by',
        'publish',
        'simulate_exits',
        'dry_run',
        'broadcast',
        'total_signals',
        'total_created',
        'results',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'symbols' => 'array',
        'fallbacks' => 'array',
        'publish' => 'boolean',
        'simulate_exits' => 'boolean',
        'dry_run' => 'boolean',
        'broadcast' => 'boolean',
        'total_signals' => 'integer',
        'total_created' => 'integer',
        'limit' => 'integer',
        'context_limit' => 'integer',
        'start_time_ms' => 'integer',
        'end_time_ms' => 'integer',
        'results' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
