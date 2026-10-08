<?php

namespace App\Services\Signals;

use App\Models\SignalFilterPreset;
use App\Repositories\Signals\SignalFilterPresetRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class SignalFilterPresetService
{
    public function __construct(
        private readonly SignalFilterPresetRepository $presets,
    ) {}

    /**
     * @return Collection<int, SignalFilterPreset>
     */
    public function listForUser(int $userId): Collection
    {
        if ($userId <= 0) {
            return collect();
        }

        return $this->presets->listForUser($userId);
    }

    public function create(int $userId, array $payload): SignalFilterPreset
    {
        $data = Validator::validate($payload, [
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
        ]);

        return $this->presets->createForUser($userId, $data['name'], $data['filters']);
    }

    public function find(int $userId, int $presetId): ?SignalFilterPreset
    {
        return $this->presets->findForUser($userId, $presetId);
    }

    public function delete(int $userId, int $presetId): void
    {
        $this->presets->deleteForUser($userId, $presetId);
    }
}
