<?php

namespace App\Events;

use App\Models\Signal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SignalPublished implements ShouldBroadcast
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
        return 'signal.published';
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
            'published_at' => optional($this->signal->published_at)->toISOString(),
        ];
    }
}
