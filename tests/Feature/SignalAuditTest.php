<?php

namespace Tests\Feature;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Enums\SignalUpdateType;
use App\Models\Signal;
use App\Models\SignalUpdate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_signal_creation_sets_created_by_from_actor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $signal = $this->makeSignal([
            'created_by' => null,
            'status' => SignalStatus::Draft,
            'published_at' => null,
            'expires_at' => null,
        ]);

        $this->assertSame($user->id, $signal->created_by);
    }

    public function test_published_update_records_actor(): void
    {
        $creator = User::factory()->create();
        $publisher = User::factory()->create();

        $signal = $this->makeSignal([
            'created_by' => $creator->id,
            'status' => SignalStatus::Draft,
            'published_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($publisher);
        $signal->forceFill([
            'status' => SignalStatus::Published,
            'published_at' => now(),
        ])->save();

        $update = SignalUpdate::query()
            ->where('signal_id', $signal->id)
            ->where('type', SignalUpdateType::Published)
            ->latest('id')
            ->first();

        $this->assertNotNull($update);
        $this->assertSame($publisher->id, $update->created_by);
    }

    public function test_closed_update_records_actor(): void
    {
        $creator = User::factory()->create();
        $closer = User::factory()->create();

        $signal = $this->makeSignal([
            'created_by' => $creator->id,
            'status' => SignalStatus::Published,
            'published_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($closer);
        $signal->forceFill([
            'status' => SignalStatus::Closed,
            'closed_at' => now(),
        ])->save();

        $update = SignalUpdate::query()
            ->where('signal_id', $signal->id)
            ->where('type', SignalUpdateType::Closed)
            ->latest('id')
            ->first();

        $this->assertNotNull($update);
        $this->assertSame($closer->id, $update->created_by);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeSignal(array $overrides): Signal
    {
        return Signal::query()->create(array_merge([
            'signal_category_id' => null,
            'created_by' => null,
            'strategy_key' => 'smart_volume_v1',
            'strategy_params' => null,
            'exchange' => 'binance',
            'symbol' => 'BTCUSDT',
            'timeframe' => '1h',
            'direction' => SignalDirection::Long,
            'entry_at' => now()->subHour(),
            'entry_price' => 60000.0,
            'stop_loss' => 59000.0,
            'take_profit' => 63000.0,
            'reward_risk' => 2.5,
            'status' => SignalStatus::Draft,
            'published_at' => null,
            'expires_at' => null,
            'closed_at' => null,
            'close_reason' => null,
            'close_price' => null,
            'notes' => null,
            'debug' => null,
        ], $overrides));
    }
}
