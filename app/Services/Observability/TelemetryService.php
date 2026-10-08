<?php

namespace App\Services\Observability;

use Illuminate\Support\Facades\Log;

class TelemetryService
{
    /**
     * @param array<string, mixed> $context
     */
    public function event(string $name, array $context = []): void
    {
        $this->write('event', $name, $context);
    }

    /**
     * @param array<string, mixed> $tags
     */
    public function metric(string $name, float|int $value = 1, array $tags = []): void
    {
        $this->write('metric', $name, [
            'value' => $value,
            'tags' => $tags,
        ]);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function alert(string $name, array $context = [], string $level = 'warning'): void
    {
        $this->write('alert', $name, $context, $level);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $type, string $name, array $context, string $level = 'info'): void
    {
        Log::channel('telemetry')->log($level, 'telemetry', [
            'type' => $type,
            'name' => $name,
            'context' => $context,
        ]);
    }
}
