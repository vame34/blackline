<?php

namespace Tests\Feature;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Models\ApiToken;
use App\Models\Signal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiSignalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_signals_api_requires_token(): void
    {
        $response = $this->getJson('/api/v1/signals');

        $response
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHORIZED');
    }

    public function test_signals_api_is_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals');

        $response
            ->assertOk();
    }

    public function test_signals_api_returns_paginated_data(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $this->createSignal('BTCUSDT', SignalStatus::Published, now()->subMinutes(10));
        $this->createSignal('ETHUSDT', SignalStatus::Closed, now()->subMinutes(20));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals?status=all&per_page=5');

        $response
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('money_management.model', 'terminal_backtest_ladder')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'symbol', 'direction', 'status', 'published_at'],
                ],
                'money_management' => ['model', 'version', 'risk_ladder', 'entry_rules'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $symbols = collect($response->json('data'))->pluck('symbol')->all();
        $this->assertContains('BTCUSDT', $symbols);
        $this->assertContains('ETHUSDT', $symbols);
    }

    public function test_signals_api_denies_request_from_untrusted_ip(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user, '203.0.113.10');

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
        ])->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals');

        $response
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'IP_NOT_ALLOWED');
    }

    public function test_signals_api_allows_request_from_trusted_ip(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user, '203.0.113.10');

        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '203.0.113.10',
        ])->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals');

        $response->assertOk();
    }

    public function test_signals_stream_returns_sse_payload(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'text/event-stream',
        ])->get('/api/v1/signals/stream?timeout=1&poll_ms=500');

        $response->assertOk();
        $contentType = (string) $response->headers->get('Content-Type', '');
        $this->assertStringContainsString('text/event-stream', strtolower($contentType));

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: ready', $content);
        $this->assertStringContainsString('event: end', $content);
        $this->assertStringContainsString('"money_management"', $content);
    }

    public function test_signals_api_accepts_x_api_key_header(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $response = $this->withHeaders([
            'X-API-Key' => $token,
        ])->getJson('/api/v1/signals');

        $response->assertOk();
    }

    public function test_stream_endpoint_is_rate_limited_separately(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);
        config()->set('api.limits.stream_requests_per_minute', 1);

        $first = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'text/event-stream',
        ])->get('/api/v1/signals/stream?timeout=1&poll_ms=500');
        $first->assertOk();

        $second = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'text/event-stream',
        ])->get('/api/v1/signals/stream?timeout=1&poll_ms=500');
        $second->assertStatus(429);
    }

    public function test_signals_api_rejects_invalid_period(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals?period=999d');

        $response->assertStatus(422);
    }

    public function test_signals_api_supports_period_filter(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $recentSymbol = 'BLTRECENTUSDT';
        $oldSymbol = 'BLTOLDUSDT';

        $this->createSignal($recentSymbol, SignalStatus::Published, now()->subDays(3));
        $this->createSignal($oldSymbol, SignalStatus::Published, now()->subDays(20));

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/signals?period=7d&status=all&symbol=BLT');

        $response->assertOk();
        $symbols = collect($response->json('data'))->pluck('symbol')->all();
        $this->assertContains($recentSymbol, $symbols);
        $this->assertNotContains($oldSymbol, $symbols);
    }

    public function test_instruments_endpoint_returns_symbols(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/instruments');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['symbol', 'label', 'tradingview', 'exchange'],
                ],
            ]);
    }

    public function test_risk_model_endpoints_return_versioned_model(): void
    {
        $user = User::factory()->create();
        $token = $this->createApiTokenFor($user);

        $modelResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/risk-model');

        $modelResponse
            ->assertOk()
            ->assertJsonPath('data.model', 'terminal_backtest_ladder')
            ->assertJsonStructure([
                'data' => [
                    'model',
                    'version',
                    'scope',
                    'leverage_bounds' => ['min', 'max'],
                    'risk_ladder',
                    'position_sizing',
                    'execution_notes',
                ],
            ]);

        $versionResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/risk-model/version');

        $versionResponse
            ->assertOk()
            ->assertJsonPath('data.model', 'terminal_backtest_ladder')
            ->assertJsonPath('data.version', $modelResponse->json('data.version'));
    }

    private function createApiTokenFor(User $user, ?string $trustedIp = null): string
    {
        $issued = ApiToken::issueToken();

        ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => $issued['hash'],
            'expires_at' => now()->addDay(),
            'trusted_ip' => $trustedIp,
        ]);

        return $issued['plain_text'];
    }

    private function createSignal(string $symbol, SignalStatus $status, $publishedAt): void
    {
        Signal::query()->create([
            'signal_category_id' => null,
            'created_by' => null,
            'strategy_key' => 'smart_volume_v1',
            'strategy_params' => null,
            'exchange' => 'binance',
            'symbol' => $symbol,
            'timeframe' => '1h',
            'direction' => SignalDirection::Long,
            'entry_at' => now()->subHour(),
            'entry_price' => 60000.0,
            'stop_loss' => 59000.0,
            'take_profit' => 62000.0,
            'reward_risk' => 2.0,
            'status' => $status,
            'published_at' => $publishedAt,
            'expires_at' => now()->addDay(),
            'closed_at' => $status === SignalStatus::Closed ? now()->subMinute() : null,
            'close_reason' => null,
            'close_price' => $status === SignalStatus::Closed ? 61000.0 : null,
            'notes' => null,
            'debug' => null,
        ]);
    }
}
