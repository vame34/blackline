<?php

namespace App\Http\Middleware;

use App\Services\Observability\TelemetryService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiSecurityTelemetry
{
    public function __construct(
        private readonly TelemetryService $telemetry,
    ) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);
        $response = $next($request);
        $durationMs = (int) round((microtime(true) - $started) * 1000);
        $status = (int) $response->getStatusCode();

        $routeUri = (string) ($request->route()?->uri() ?? $request->path());
        $method = strtoupper($request->method());
        $tokenId = (string) $request->attributes->get('api_token_id', '');
        $ip = (string) $request->ip();

        $tags = [
            'route' => $routeUri,
            'method' => $method,
            'status' => (string) $status,
        ];

        $this->telemetry->metric('api.request.total', 1, $tags);
        $this->telemetry->metric('api.request.duration_ms', $durationMs, $tags);

        if ($status === 401) {
            $this->telemetry->alert('api.security.auth_failed', [
                'route' => $routeUri,
                'method' => $method,
                'ip' => $ip,
                'token_id' => $tokenId !== '' ? $tokenId : null,
            ]);
        } elseif ($status === 403) {
            $this->telemetry->alert('api.security.access_denied', [
                'route' => $routeUri,
                'method' => $method,
                'ip' => $ip,
                'token_id' => $tokenId !== '' ? $tokenId : null,
            ]);
        } elseif ($status === 429) {
            $this->telemetry->alert('api.security.rate_limited', [
                'route' => $routeUri,
                'method' => $method,
                'ip' => $ip,
                'token_id' => $tokenId !== '' ? $tokenId : null,
            ]);
        }

        return $response;
    }
}

