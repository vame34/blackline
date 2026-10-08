<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PerformanceSnapshot extends Model
{
    protected $fillable = [
        'scope',
        'scope_key',
        'metrics',
        'period_start',
        'period_end',
    ];

    protected $casts = [
        'metrics' => 'array',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];
}
