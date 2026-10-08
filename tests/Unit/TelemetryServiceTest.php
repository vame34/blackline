<?php

namespace Tests\Unit;

use App\Services\Observability\TelemetryService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TelemetryServiceTest extends TestCase
{
    public function test_event_logs_to_telemetry_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('telemetry')->andReturnSelf();
        Log::shouldReceive('log')->once()->with(
            'info',
            'telemetry',
            Mockery::on(function (array $payload): bool {
                return $payload['type'] === 'event'
                    && $payload['name'] === 'signals.update.created'
                    && $payload['context']['signal_id'] === 42;
            }),
        );

        (new TelemetryService)->event('signals.update.created', [
            'signal_id' => 42,
        ]);
    }

    public function test_metric_logs_to_telemetry_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('telemetry')->andReturnSelf();
        Log::shouldReceive('log')->once()->with(
            'info',
            'telemetry',
            Mockery::on(function (array $payload): bool {
                return $payload['type'] === 'metric'
                    && $payload['name'] === 'signals.import.completed'
                    && $payload['context']['value'] === 1
                    && $payload['context']['tags']['status'] === 'completed';
            }),
        );

        (new TelemetryService)->metric('signals.import.completed', 1, [
            'status' => 'completed',
        ]);
    }

    public function test_alert_logs_to_telemetry_channel(): void
    {
        Log::shouldReceive('channel')->once()->with('telemetry')->andReturnSelf();
        Log::shouldReceive('log')->once()->with(
            'error',
            'telemetry',
            Mockery::on(function (array $payload): bool {
                return $payload['type'] === 'alert'
                    && $payload['name'] === 'signals.import.failed'
                    && $payload['context']['batch_id'] === 'order-1';
            }),
        );

        (new TelemetryService)->alert('signals.import.failed', [
            'batch_id' => 'order-1',
        ], 'error');
    }
}
