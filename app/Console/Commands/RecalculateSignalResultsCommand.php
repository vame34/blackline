<?php

namespace App\Console\Commands;

use App\Enums\SignalStatus;
use App\Models\Signal;
use Illuminate\Console\Command;

class RecalculateSignalResultsCommand extends Command
{
    protected $signature = 'signals:recalc-results
        {--chunk=500 : Batch size}
        {--all : Recalculate all closed signals}';

    protected $description = 'Recalculate stored result_r and result_pct for closed signals.';

    public function handle(): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $recalculateAll = (bool) $this->option('all');

        $query = Signal::query()
            ->where('status', SignalStatus::Closed)
            ->whereNotNull('close_price');

        if (! $recalculateAll) {
            $query->where(function ($query): void {
                $query->whereNull('result_r')->orWhereNull('result_pct');
            });
        }

        $total = 0;
        $updated = 0;

        $query->orderBy('id')->chunkById($chunk, function ($signals) use (&$total, &$updated): void {
            $total += $signals->count();

            Signal::withoutEvents(function () use ($signals, &$updated): void {
                foreach ($signals as $signal) {
                    $resultR = $signal->calculateResultR();
                    $resultPct = $signal->calculateResultPct();

                    $normalizedR = $resultR !== null ? round($resultR, 4) : null;
                    $normalizedPct = $resultPct !== null ? round($resultPct, 4) : null;

                    $currentR = $signal->result_r !== null ? round((float) $signal->result_r, 4) : null;
                    $currentPct = $signal->result_pct !== null ? round((float) $signal->result_pct, 4) : null;

                    if ($currentR === $normalizedR && $currentPct === $normalizedPct) {
                        continue;
                    }

                    $signal->forceFill([
                        'result_r' => $normalizedR,
                        'result_pct' => $normalizedPct,
                    ])->save();

                    $updated += 1;
                }
            });
        });

        $this->info("Signals scanned: {$total}, updated: {$updated}");

        return self::SUCCESS;
    }
}
