<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ApiSignalsLoadTestCommand extends Command
{
    protected $signature = 'api:signals:load-test
        {--base-url=https://black-line.online/api/v1 : API base URL}
        {--token= : API token}
        {--duration=30 : Test duration in seconds}
        {--concurrency=8 : Concurrent workers}
        {--stream-ratio=20 : Percent of requests sent to /signals/stream}';

    protected $description = 'Run API load test (REST + stream) and print latency/status summary.';

    public function handle(): int
    {
        $baseUrl = rtrim((string) $this->option('base-url'), '/');
        $token = trim((string) $this->option('token'));
        $duration = max(5, (int) $this->option('duration'));
        $concurrency = max(1, min(64, (int) $this->option('concurrency')));
        $streamRatio = max(0, min(100, (int) $this->option('stream-ratio')));

        if ($token === '') {
            $this->error('Missing token. Pass --token=YOUR_API_KEY');
            return self::FAILURE;
        }

        $this->line('Base URL: '.$baseUrl);
        $this->line('Duration: '.$duration.'s; Concurrency: '.$concurrency.'; Stream ratio: '.$streamRatio.'%');

        $deadline = microtime(true) + $duration;
        $statusCounts = [];
        $latenciesMs = [];
        $errors = 0;
        $total = 0;

        while (microtime(true) < $deadline) {
            $mh = curl_multi_init();
            $handles = [];

            for ($i = 0; $i < $concurrency; $i += 1) {
                $useStream = random_int(1, 100) <= $streamRatio;
                $path = $useStream
                    ? '/signals/stream?timeout=1&poll_ms=500'
                    : '/signals?status=all&per_page=20&page=1';

                $ch = curl_init($baseUrl.$path);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer '.$token,
                        'Accept: '.($useStream ? 'text/event-stream' : 'application/json'),
                    ],
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT => $useStream ? 4 : 10,
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[] = $ch;
            }

            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh, 0.5);
                }
            } while ($active && $status === CURLM_OK);

            foreach ($handles as $ch) {
                $total += 1;
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $totalTimeMs = (float) curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
                $errno = curl_errno($ch);

                if ($errno !== 0) {
                    $errors += 1;
                }

                $statusCounts[$httpCode] = ($statusCounts[$httpCode] ?? 0) + 1;
                $latenciesMs[] = $totalTimeMs;

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        sort($latenciesMs);
        $p50 = $this->percentile($latenciesMs, 50);
        $p95 = $this->percentile($latenciesMs, 95);
        $p99 = $this->percentile($latenciesMs, 99);
        $rps = $duration > 0 ? round($total / $duration, 2) : 0.0;

        ksort($statusCounts);

        $this->newLine();
        $this->info('Load test summary');
        $this->line('Total requests: '.$total);
        $this->line('RPS: '.$rps);
        $this->line('Errors: '.$errors);
        $this->line('Latency p50/p95/p99 (ms): '.round($p50, 2).' / '.round($p95, 2).' / '.round($p99, 2));
        $this->line('HTTP status distribution:');
        foreach ($statusCounts as $code => $count) {
            $this->line('  '.$code.': '.$count);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, float> $values
     */
    private function percentile(array $values, int $percent): float
    {
        if ($values === []) {
            return 0.0;
        }

        $index = (int) floor(((max(1, min(100, $percent)) / 100) * (count($values) - 1)));
        return (float) $values[$index];
    }
}

