<?php

namespace App\Repositories\Signals;

use App\Models\SignalUpdate;
use Illuminate\Support\Collection;

class EloquentSignalUpdateRepository implements SignalUpdateRepository
{
    public function latestForSignal(int $signalId, int $limit = 20): Collection
    {
        return SignalUpdate::query()
            ->where('signal_id', $signalId)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function create(array $data): SignalUpdate
    {
        return SignalUpdate::query()->create($data);
    }
}
