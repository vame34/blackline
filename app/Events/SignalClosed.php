<?php

namespace App\Events;

use App\Models\Signal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignalClosed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly Signal $signal,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('signals.subscribers'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'signal.closed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->signal->id,
            'exchange' => $this->signal->exchange,
            'strategy_key' => $this->signal->strategy_key,
            'symbol' => $this->signal->symbol,
            'timeframe' => $this->signal->timeframe,
            'direction' => $this->signal->direction?->value,
            'closed_at' => optional($this->signal->closed_at)->toISOString(),
            'close_price' => $this->signal->close_price !== null ? (float) $this->signal->close_price : null,
            'close_reason' => $this->signal->close_reason,
        ];
    }
}
