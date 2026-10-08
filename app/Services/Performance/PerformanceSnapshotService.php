<?php

namespace App\Services\Performance;

use App\Models\PerformanceSnapshot;
use App\Repositories\Performance\PerformanceSnapshotRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

class PerformanceSnapshotService
{
    public function __construct(
        private readonly PerformanceSnapshotRepository $snapshots,
    ) {}

    public function latest(string $scope, ?string $scopeKey = null): ?PerformanceSnapshot
    {
        return $this->snapshots->latestForScope($scope, $scopeKey);
    }

    public function upsert(array $payload): PerformanceSnapshot
    {
        $data = Validator::validate($payload, [
            'scope' => ['required', 'string', 'max:32'],
            'scope_key' => ['nullable', 'string', 'max:64'],
            'metrics' => ['required', 'array'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date'],
        ]);

        return $this->snapshots->upsertSnapshot(
            scope: $data['scope'],
            scopeKey: $data['scope_key'] ?? null,
            metrics: $data['metrics'],
            periodStart: isset($data['period_start']) ? Carbon::parse($data['period_start']) : null,
            periodEnd: isset($data['period_end']) ? Carbon::parse($data['period_end']) : null,
        );
    }
}
