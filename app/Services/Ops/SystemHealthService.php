<?php

namespace App\Services\Ops;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Throwable;

class SystemHealthService
{
    public const SCHEDULER_HEARTBEAT_KEY = 'system.scheduler_heartbeat';
    public const SCHEDULER_HEARTBEAT_TTL = 120;

    public function touchSchedulerHeartbeat(): void
    {
        Cache::put(self::SCHEDULER_HEARTBEAT_KEY, now()->getTimestamp(), self::SCHEDULER_HEARTBEAT_TTL);
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'horizon' => $this->checkHorizon(),
            'scheduler' => $this->checkScheduler(),
            'reverb' => $this->checkReverb(),
        ];

        $ok = collect($checks)
            ->every(fn (array $check): bool => in_array($check['status'], ['ok', 'skipped'], true));

        return [
            'ok' => $ok,
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->select('select 1');

            return ['status' => 'ok'];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'error' => $this->formatError($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkRedis(): array
    {
        try {
            $response = Redis::connection()->ping();

            return [
                'status' => 'ok',
                'response' => $response,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'error' => $this->formatError($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkQueue(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $driver = (string) config("queue.connections.{$connection}.driver", 'sync');

        if ($driver === 'sync') {
            return [
                'status' => 'skipped',
                'driver' => $driver,
                'connection' => $connection,
            ];
        }

        try {
            if ($driver === 'redis') {
                $redisConnection = (string) config("queue.connections.{$connection}.connection", 'default');
                Redis::connection($redisConnection)->ping();

                return [
                    'status' => 'ok',
                    'driver' => $driver,
                    'connection' => $connection,
                    'redis_connection' => $redisConnection,
                ];
            }

            if ($driver === 'database') {
                $dbConnection = config("queue.connections.{$connection}.connection");
                DB::connection($dbConnection)->select('select 1');

                return [
                    'status' => 'ok',
                    'driver' => $driver,
                    'connection' => $connection,
                    'db_connection' => $dbConnection,
                ];
            }

            return [
                'status' => 'skipped',
                'driver' => $driver,
                'connection' => $connection,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'driver' => $driver,
                'connection' => $connection,
                'error' => $this->formatError($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkHorizon(): array
    {
        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            if (! $masters) {
                return [
                    'status' => 'warn',
                    'state' => 'inactive',
                ];
            }

            $paused = collect($masters)
                ->every(fn ($master): bool => $master->status === 'paused');

            return [
                'status' => $paused ? 'warn' : 'ok',
                'state' => $paused ? 'paused' : 'running',
                'masters' => count($masters),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'fail',
                'error' => $this->formatError($e),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkScheduler(): array
    {
        $lastSeen = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

        if (! $lastSeen) {
            return [
                'status' => 'warn',
                'last_seen' => null,
            ];
        }

        $age = now()->getTimestamp() - (int) $lastSeen;
        $stale = $age > self::SCHEDULER_HEARTBEAT_TTL;

        return [
            'status' => $stale ? 'warn' : 'ok',
            'last_seen' => now()->setTimestamp((int) $lastSeen)->toIso8601String(),
            'age_seconds' => $age,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkReverb(): array
    {
        $appConfig = (array) config('reverb.apps.apps.0', []);
        $options = (array) ($appConfig['options'] ?? []);

        $host = $options['host'] ?? null;
        $port = $options['port'] ?? null;

        if (! $host || ! $port) {
            return [
                'status' => 'skipped',
            ];
        }

        if (is_string($host) && str_contains($host, '://')) {
            $parsed = parse_url($host);
            $host = $parsed['host'] ?? $host;
            $port = $parsed['port'] ?? $port;
        }

        $host = (string) $host;
        $port = (int) $port;

        if ($host === '' || $port === 0) {
            return [
                'status' => 'skipped',
            ];
        }

        $timeout = 1.0;
        $errno = 0;
        $errstr = '';
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($connection) {
            fclose($connection);

            return [
                'status' => 'ok',
                'host' => $host,
                'port' => $port,
            ];
        }

        return [
            'status' => 'fail',
            'host' => $host,
            'port' => $port,
            'error' => $errstr !== '' ? $errstr : 'connection failed',
        ];
    }

    private function formatError(Throwable $e): string
    {
        if ((bool) config('app.debug')) {
            return $e->getMessage();
        }

        return $e::class;
    }
}
