<?php

namespace App\Http\Resources;

use App\Models\Signal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Signal */
class SignalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exchange' => $this->exchange,
            'symbol' => $this->symbol,
            'timeframe' => $this->timeframe,
            'direction' => $this->direction?->value,
            'status' => $this->status?->value,
            'strategy_key' => $this->strategy_key,
            'entry_at' => optional($this->entry_at)->toISOString(),
            'published_at' => optional($this->published_at)->toISOString(),
            'expires_at' => optional($this->expires_at)->toISOString(),
            'closed_at' => optional($this->closed_at)->toISOString(),
            'entry_price' => $this->numeric($this->entry_price),
            'stop_loss' => $this->numeric($this->stop_loss),
            'take_profit' => $this->numeric($this->take_profit),
            'close_price' => $this->numeric($this->close_price),
            'reward_risk' => $this->numeric($this->reward_risk),
            'result_r' => $this->resultR(),
            'result_pct' => $this->resultPct(),
            'risk_percent' => $this->riskPercent(),
            'entry_zone' => $this->entryZone(),
            'take_profit_targets' => $this->takeProfitTargets(),
            'close_reason' => $this->close_reason,
            'notes' => $this->notes,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'slug' => $this->category?->slug,
            ]),
            'updates' => $this->whenLoaded('updates', function () {
                return $this->updates
                    ->sortByDesc('published_at')
                    ->take(20)
                    ->map(fn ($update) => [
                        'id' => $update->id,
                        'type' => $update->type?->value,
                        'title' => $update->title,
                        'body' => $update->body,
                        'payload' => $update->payload,
                        'published_at' => optional($update->published_at)->toISOString(),
                    ])
                    ->values();
            }),
        ];
    }

    private function numeric(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
