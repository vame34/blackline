<?php

namespace App\Repositories\Signals;

use App\Models\SignalFilterPreset;
use Illuminate\Support\Collection;

class EloquentSignalFilterPresetRepository implements SignalFilterPresetRepository
{
    public function listForUser(int $userId): Collection
    {
        return SignalFilterPreset::query()
            ->where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();
    }

    public function createForUser(int $userId, string $name, array $filters): SignalFilterPreset
    {
        return SignalFilterPreset::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'name' => $name,
            ],
            [
                'filters' => $filters,
            ],
        );
    }

    public function findForUser(int $userId, int $presetId): ?SignalFilterPreset
    {
        return SignalFilterPreset::query()
            ->where('user_id', $userId)
            ->whereKey($presetId)
            ->first();
    }

    public function deleteForUser(int $userId, int $presetId): void
    {
        SignalFilterPreset::query()
            ->where('user_id', $userId)
            ->whereKey($presetId)
            ->delete();
    }
}
