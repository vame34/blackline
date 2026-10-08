<?php

namespace App\Models;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Signal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'signal_category_id',
        'created_by',
        'strategy_key',
        'strategy_params',
        'exchange',
        'symbol',
        'timeframe',
        'direction',
        'entry_at',
        'entry_price',
        'stop_loss',
        'take_profit',
        'reward_risk',
        'status',
        'published_at',
        'expires_at',
        'closed_at',
        'close_reason',
        'close_price',
        'result_r',
        'result_pct',
        'notes',
        'debug',
    ];

    protected $casts = [
        'direction' => SignalDirection::class,
        'status' => SignalStatus::class,
        'entry_at' => 'datetime',
        'entry_price' => 'decimal:8',
        'stop_loss' => 'decimal:8',
        'take_profit' => 'decimal:8',
        'reward_risk' => 'decimal:2',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
        'close_price' => 'decimal:8',
        'result_r' => 'decimal:4',
        'result_pct' => 'decimal:4',
        'strategy_params' => 'array',
        'debug' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SignalCategory::class, 'signal_category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(SignalUpdate::class);
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function entryZone(float $percent = 0.003): ?array
    {
        $entry = $this->toFloat($this->entry_price);
        if (!$entry) {
            return null;
        }

        $delta = $entry * $percent;

        return [$entry - $delta, $entry + $delta];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    public function takeProfitTargets(): ?array
    {
        $entry = $this->toFloat($this->entry_price);
        $tp2 = $this->toFloat($this->take_profit);
        if (!$entry || !$tp2) {
            return null;
        }

        $tp1 = $entry + (($tp2 - $entry) * 0.5);

        return [$tp1, $tp2];
    }

    public function riskPercent(): ?float
    {
        $entry = $this->toFloat($this->entry_price);
        $stop = $this->toFloat($this->stop_loss);
        if (!$entry || !$stop) {
            return null;
        }

        return abs($entry - $stop) / $entry;
    }

    public function resultR(): ?float
    {
        if ($this->result_r !== null) {
            return (float) $this->result_r;
        }

        return $this->calculateResultR();
    }

    public function resultPct(): ?float
    {
        if ($this->result_pct !== null) {
            return (float) $this->result_pct;
        }

        return $this->calculateResultPct();
    }

    public function calculateResultR(): ?float
    {
        $entry = $this->toFloat($this->entry_price);
        $stop = $this->toFloat($this->stop_loss);
        $close = $this->toFloat($this->close_price);
        if (!$entry || !$stop || !$close) {
            return null;
        }

        $risk = abs($entry - $stop);
        if ($risk <= 0.0) {
            return null;
        }

        $reward = $this->direction === SignalDirection::Long
            ? $close - $entry
            : $entry - $close;

        return $reward / $risk;
    }

    public function calculateResultPct(): ?float
    {
        $entry = $this->toFloat($this->entry_price);
        $close = $this->toFloat($this->close_price);
        if (!$entry || !$close) {
            return null;
        }

        $move = $this->direction === SignalDirection::Long
            ? $close - $entry
            : $entry - $close;

        return $move / $entry;
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = (float) $value;

        return $float !== 0.0 ? $float : null;
    }
}
