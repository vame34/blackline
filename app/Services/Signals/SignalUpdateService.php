<?php

namespace App\Services\Signals;

use App\Enums\SignalUpdateType;
use App\Models\SignalUpdate;
use App\Repositories\Signals\SignalUpdateRepository;
use App\Services\Observability\TelemetryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SignalUpdateService
{
    public function __construct(
        private readonly SignalUpdateRepository $updates,
        private readonly TelemetryService $telemetry,
    ) {}

    public function create(array $payload): SignalUpdate
    {
        $data = Validator::validate($payload, [
            'signal_id' => ['required', 'integer', 'exists:signals,id'],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::enum(SignalUpdateType::class)],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['published_at'] = $data['published_at'] ?? Carbon::now();

        $update = $this->updates->create($data);
        $this->telemetry->event('signals.update.created', [
            'signal_id' => $update->signal_id,
            'update_id' => $update->id,
            'type' => $update->type->value,
        ]);
        $this->telemetry->metric('signals.update.created', 1, [
            'type' => $update->type->value,
        ]);

        return $update;
    }

    /**
     * @return Collection<int, SignalUpdate>
     */
    public function latestForSignal(int $signalId, int $limit = 20): Collection
    {
        return $this->updates->latestForSignal($signalId, $limit);
    }
}
