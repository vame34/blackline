<?php

namespace App\Repositories\Signals;

use App\Models\SignalFilterPreset;
use Illuminate\Support\Collection;

interface SignalFilterPresetRepository
{
    /**
     * @return Collection<int, SignalFilterPreset>
     */
    public function listForUser(int $userId): Collection;

    public function createForUser(int $userId, string $name, array $filters): SignalFilterPreset;

    public function findForUser(int $userId, int $presetId): ?SignalFilterPreset;

    public function deleteForUser(int $userId, int $presetId): void;
}
