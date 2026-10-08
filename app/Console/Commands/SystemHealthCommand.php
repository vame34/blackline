<?php

namespace App\Console\Commands;

use App\Services\Ops\SystemHealthService;
use Illuminate\Console\Command;

class SystemHealthCommand extends Command
{
    protected $signature = 'system:health {--json} {--pretty}';

    protected $description = 'Check health of queue, scheduler, and realtime stack.';

    public function handle(SystemHealthService $service): int
    {
        $status = $service->status();

        if ($this->option('json') || $this->option('pretty')) {
            $flags = $this->option('pretty') ? JSON_PRETTY_PRINT : 0;
            $this->line((string) json_encode($status, $flags));

            return $status['ok'] ? self::SUCCESS : self::FAILURE;
        }

        $this->line($status['ok'] ? 'OK' : 'NOT OK');

        foreach ($status['checks'] as $name => $check) {
            $this->line(sprintf('%s: %s', $name, $check['status']));
        }

        return $status['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
