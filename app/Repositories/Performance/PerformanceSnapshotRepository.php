<?php

namespace App\Repositories\Performance;

use Illuminate\Support\Carbon;

interface PerformanceSnapshotRepository
{
    public function latestForScope(string $scope, ?string $scopeKey = null): ?\App\Models\PerformanceSnapshot;

    public function upsertSnapshot(
        string $scope,
        ?string $scopeKey,
        array $metrics,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
    ): \App\Models\PerformanceSnapshot;
}
