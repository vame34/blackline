<?php

namespace Tests\Unit;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SignalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_can_view_published_signal(): void
    {
        $user = User::factory()->create();
        $signal = $this->makeSignal([
            'status' => SignalStatus::Published,
            'published_at' => now()->subMinute(),
            'expires_at' => now()->addHour(),
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('view', $signal));
    }

    public function test_non_admin_cannot_view_draft_signal(): void
    {
        $user = User::factory()->create();
        $signal = $this->makeSignal([
            'status' => SignalStatus::Draft,
            'published_at' => null,
            'expires_at' => null,
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $signal));
    }

    public function test_non_admin_cannot_view_expired_signal(): void
    {
        $user = User::factory()->create();
        $signal = $this->makeSignal([
            'status' => SignalStatus::Published,
            'published_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertFalse(Gate::forUser($user)->allows('view', $signal));
    }

    public function test_admin_can_view_any_signal(): void
    {
        $admin = User::factory()->create();
        Role::findOrCreate('admin');
        $admin->assignRole('admin');

        $signal = $this->makeSignal([
            'status' => SignalStatus::Draft,
            'published_at' => null,
            'expires_at' => null,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('view', $signal));
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
            'status' => SignalStatus::Published,
            'published_at' => now()->subMinute(),
            'expires_at' => now()->addDay(),
            'closed_at' => null,
            'close_reason' => null,
            'close_price' => null,
            'notes' => null,
            'debug' => null,
        ], $overrides));
    }
}
