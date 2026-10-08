<?php

namespace App\Repositories\Signals;

use Illuminate\Support\Collection;

interface SignalUpdateRepository
{
    /**
     * @return Collection<int, \App\Models\SignalUpdate>
     */
    public function latestForSignal(int $signalId, int $limit = 20): Collection;

    public function create(array $data): \App\Models\SignalUpdate;
}
