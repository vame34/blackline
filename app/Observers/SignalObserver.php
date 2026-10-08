<?php

namespace App\Observers;

use App\Enums\SignalStatus;
use App\Enums\SignalUpdateType;
use App\Events\SignalClosed;
use App\Events\SignalPublished;
use App\Models\Signal;
use App\Services\Signals\SignalUpdateService;
use Illuminate\Support\Facades\DB;

class SignalObserver
{
    public function saving(Signal $signal): void
    {
        $status = $signal->status instanceof SignalStatus
            ? $signal->status->value
            : (string) $signal->status;

        if ($status !== SignalStatus::Closed->value || $signal->close_price === null) {
            if ($signal->isDirty(['status', 'close_price', 'entry_price', 'stop_loss', 'direction'])) {
                $signal->result_r = null;
                $signal->result_pct = null;
            }

            return;
        }

        $signal->result_r = $signal->calculateResultR();
        $signal->result_pct = $signal->calculateResultPct();
    }

    public function creating(Signal $signal): void
    {
        if ($signal->created_by !== null) {
            return;
        }

        $userId = auth()->id();
        if ($userId) {
            $signal->created_by = $userId;
        }
    }

    public function created(Signal $signal): void
    {
        if ($signal->published_at === null) {
            return;
        }

        $this->dispatchPublishedAfterCommit($signal->id);
        $this->recordUpdateAfterCommit(
            $signal->id,
            SignalUpdateType::Published,
            'Сигнал опубликован',
            $this->resolveActorId($signal->created_by),
        );
    }

    public function updated(Signal $signal): void
    {
        if ($signal->published_at !== null) {
            $original = $signal->getOriginal('published_at');
            if ($original === null) {
                $this->dispatchPublishedAfterCommit($signal->id);
                $this->recordUpdateAfterCommit(
                    $signal->id,
                    SignalUpdateType::Published,
                    'Сигнал опубликован',
                    $this->resolveActorId($signal->created_by),
                );
            }
        }

        $closedByStatus = $signal->wasChanged('status') && $signal->status->value === 'closed';
        $closedByDate = $signal->wasChanged('closed_at') && $signal->closed_at !== null;

        if ($closedByStatus || $closedByDate) {
            $this->dispatchClosedAfterCommit($signal->id);
        }

        if ($closedByStatus) {
            $this->recordUpdateAfterCommit(
                $signal->id,
                SignalUpdateType::Closed,
                'Сигнал закрыт',
                $this->resolveActorId($signal->created_by),
            );
            return;
        }

        if ($closedByDate) {
            $this->recordUpdateAfterCommit(
                $signal->id,
                SignalUpdateType::Closed,
                'Сигнал закрыт',
                $this->resolveActorId($signal->created_by),
            );
        }
    }

    private function dispatchPublishedAfterCommit(int $signalId): void
    {
        DB::afterCommit(function () use ($signalId): void {
            $signal = Signal::query()->find($signalId);
            if (! $signal || $signal->published_at === null) {
                return;
            }

            event(new SignalPublished($signal));
        });
    }

    private function dispatchClosedAfterCommit(int $signalId): void
    {
        DB::afterCommit(function () use ($signalId): void {
            $signal = Signal::query()->find($signalId);
            if (! $signal || $signal->closed_at === null || $signal->status->value !== SignalStatus::Closed->value) {
                return;
            }

            event(new SignalClosed($signal));
        });
    }

    private function recordUpdateAfterCommit(
        int $signalId,
        SignalUpdateType $type,
        string $title,
        ?int $actorId = null,
    ): void
    {
        DB::afterCommit(function () use ($signalId, $type, $title, $actorId): void {
            $signal = Signal::query()->find($signalId);
            if (!$signal) {
                return;
            }

            app(SignalUpdateService::class)->create([
                'signal_id' => $signal->id,
                'created_by' => $actorId,
                'type' => $type,
                'title' => $title,
                'published_at' => now(),
            ]);
        });
    }

    private function resolveActorId(?int $fallback): ?int
    {
        return auth()->id() ?? $fallback;
    }
}
