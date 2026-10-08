<?php

namespace Tests\Unit;

use App\Http\Middleware\ApiSecurityTelemetry;
use App\Services\Observability\TelemetryService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiSecurityTelemetryMiddlewareTest extends TestCase
{
    public function test_it_tracks_total_requests_and_latency(): void
    {
        $telemetry = $this->createMock(TelemetryService::class);
        $telemetry->expects($this->exactly(2))
            ->method('metric');
        $telemetry->expects($this->never())
            ->method('alert');

        $middleware = new ApiSecurityTelemetry($telemetry);
        $request = Request::create('/api/v1/signals', 'GET');

        $response = $middleware->handle($request, static fn () => new Response('{}', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_it_sends_security_alerts_for_401_403_429(): void
    {
        $telemetry = $this->createMock(TelemetryService::class);
        $telemetry->expects($this->exactly(6))
            ->method('metric');
        $telemetry->expects($this->exactly(3))
            ->method('alert');

        $middleware = new ApiSecurityTelemetry($telemetry);

        $statuses = [401, 403, 429];
        foreach ($statuses as $status) {
            $request = Request::create('/api/v1/signals', 'GET');
            $middleware->handle($request, static fn () => new Response('{}', $status));
        }

        $this->assertTrue(true);
    }
}

