<?php

namespace App\Repositories\Performance;

use App\Models\PerformanceSnapshot;
use Illuminate\Support\Carbon;

class EloquentPerformanceSnapshotRepository implements PerformanceSnapshotRepository
{
    public function latestForScope(string $scope, ?string $scopeKey = null): ?PerformanceSnapshot
    {
        return PerformanceSnapshot::query()
            ->where('scope', $scope)
            ->when($scopeKey, fn ($query) => $query->where('scope_key', $scopeKey))
            ->latest('period_end')
            ->latest('id')
            ->first();
    }

    public function upsertSnapshot(
        string $scope,
        ?string $scopeKey,
        array $metrics,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
    ): PerformanceSnapshot {
        return PerformanceSnapshot::query()->updateOrCreate(
            [
                'scope' => $scope,
                'scope_key' => $scopeKey,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ],
            [
                'metrics' => $metrics,
            ],
        );
    }
}
