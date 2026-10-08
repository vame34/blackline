<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceService;
use App\Services\Performance\PerformanceSnapshotService;
use Illuminate\Console\Command;

class RefreshPerformanceSnapshotsCommand extends Command
{
    protected $signature = 'performance:refresh
        {--scope=global : Snapshot scope key}
        {--scope-key= : Optional scope key}';

    protected $description = 'Recalculate and store performance snapshots.';

    public function handle(
        PerformanceService $performance,
        PerformanceSnapshotService $snapshots,
    ): int {
        $scope = (string) $this->option('scope');
        $scopeKey = $this->option('scope-key') !== null ? (string) $this->option('scope-key') : null;

        $metrics = $performance->calculateGlobal();

        $snapshot = $snapshots->upsert([
            'scope' => $scope,
            'scope_key' => $scopeKey,
            'metrics' => $metrics,
        ]);

        $this->info("Snapshot saved: {$snapshot->id}");
        return self::SUCCESS;
    }
}
