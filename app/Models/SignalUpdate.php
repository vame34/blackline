<?php

namespace App\Models;

use App\Enums\SignalUpdateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SignalUpdate extends Model
{
    protected $fillable = [
        'signal_id',
        'created_by',
        'type',
        'title',
        'body',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
        'type' => SignalUpdateType::class,
    ];

    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
