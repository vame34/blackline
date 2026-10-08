<?php

namespace App\Services\Signals\Import;

use App\DTOs\Trading\Candle;
use App\DTOs\Trading\InstrumentData;
use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Models\SignalCategory;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StrategySignalImportService
{
    public function __construct(private readonly StrategyRegistry $strategies) {}

    /**
     * @param  array<int, Candle>  $candles
     * @param  array<int, Candle>  $contextCandles
     * @param  array<string, mixed>  $configOverrides
     * @return array{created: int, total: int, updated: int, wins?: int, losses?: int, flat?: int, winrate?: float, sum_pct?: float}
     */
    public function import(
        string $exchange,
        string $symbol,
        string $interval,
        array $candles,
        array $contextCandles,
        bool $publish = false,
        ?string $categorySlug = null,
        ?int $createdBy = null,
        bool $dryRun = false,
        bool $publishAtEntry = false,
        bool $publishLatestOnly = false,
        bool $simulateExits = false,
        ?int $signalStartTimeMs = null,
        ?int $signalEndTimeMs = null,
        bool $updateExistingOnly = false,
        bool $closeOnTargetOnly = false,
        array $configOverrides = [],
        bool $collectStats = false,
        ?string $strategyKey = null,
    ): array {
        $strategyKey ??= $this->strategies->activeKey();
        $configuration = $this->strategies->configuration($strategyKey, $symbol, $configOverrides);
        $adapter = $this->strategies->adapter($strategyKey);

        $categoryId = null;
        if (is_string($categorySlug) && $categorySlug !== '') {
            $categoryId = SignalCategory::query()->where('slug', $categorySlug)->value('id');
        }

        $instrumentData = new InstrumentData(
            symbol: $symbol,
            exchange: $exchange,
            interval: $interval,
            candles: $candles,
            contextCandles: $contextCandles,

        );

        $result = $adapter->generate($instrumentData, $configuration, $simulateExits);
        $signals = $result->signals;
        if ($signalStartTimeMs !== null || $signalEndTimeMs !== null) {
            $signals = array_values(array_filter(
                $signals,
                static function (object $signal) use ($signalStartTimeMs, $signalEndTimeMs): bool {
                    $entryTime = (int) ($signal->entryTimeMs ?? 0);
                    if ($signalStartTimeMs !== null && $entryTime < $signalStartTimeMs) {
                        return false;
                    }
                    if ($signalEndTimeMs !== null && $entryTime >= $signalEndTimeMs) {
                        return false;
                    }

                    return true;
                }
            ));
        }
        if ($publishLatestOnly && count($signals) > 1) {
            $latest = end($signals);
            $signals = $latest ? [$latest] : [];
        }

        if ($dryRun) {
            $payload = [
                'created' => 0,
                'total' => count($signals),
                'updated' => 0,
            ];
            if ($collectStats) {
                $payload = array_merge($payload, $this->calculateSignalStats($signals));
            }

            return $payload;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use (
            $signals,
            $strategyKey,
            $exchange,
            $interval,
            $categoryId,
            $createdBy,
            $publish,
            $publishAtEntry,
            $updateExistingOnly,
            $closeOnTargetOnly,
            &$created,
            &$updated,
        ): void {
            foreach ($signals as $dto) {
                $entryAt = Carbon::createFromTimestampMs($dto->entryTimeMs, 'UTC');
                $publishedAt = null;
                if ($publish) {
                    $publishedAt = $publishAtEntry ? $entryAt : now();
                }

                $closedAt = $dto->exitTimeMs ? Carbon::createFromTimestampMs($dto->exitTimeMs, 'UTC') : null;
                $closePrice = $dto->exitPrice;
                $closeReason = $this->resolveCloseReason($dto, $closePrice);
                $hasTargetExit = in_array($closeReason, ['tp', 'sl'], true);
                $canClose = $publish
                    && $closedAt
                    && $closePrice !== null
                    && (! $closeOnTargetOnly || $hasTargetExit);

                $status = $publish ? SignalStatus::Published : SignalStatus::Draft;
                if ($canClose) {
                    $status = SignalStatus::Closed;
                }

                $identity = [
                    'exchange' => $exchange,
                    'symbol' => $dto->symbol,
                    'timeframe' => $interval,
                    'direction' => $dto->direction,
                    'entry_at' => $entryAt,
                    'strategy_key' => $strategyKey,
                ];

                $model = null;
                if ($updateExistingOnly) {
                    $model = Signal::query()
                        ->where($identity)
                        ->first();
                    if (! $model) {
                        continue;
                    }
                } else {
                    $model = Signal::query()->firstOrCreate(
                        $identity,
                        [
                            'signal_category_id' => $categoryId,
                            'created_by' => $createdBy,
                            'entry_price' => $dto->entryPrice,
                            'stop_loss' => $dto->stopLoss,
                            'take_profit' => $dto->takeProfit,
                            'reward_risk' => $dto->rewardRisk,
                            'status' => $status,
                            'published_at' => $publishedAt,
                            'closed_at' => $status === SignalStatus::Closed ? $closedAt : null,
                            'close_price' => $status === SignalStatus::Closed ? $closePrice : null,
                            'close_reason' => $status === SignalStatus::Closed ? $closeReason : null,
                            'strategy_params' => array_merge($dto->metadata, ['execution' => (array) config('trading', [])]),
                        ],
                    );
                }

                if ($model->wasRecentlyCreated) {
                    $created += 1;

                    continue;
                }

                if (
                    $canClose
                    && ($model->status->value !== SignalStatus::Closed->value || $model->close_price === null)
                ) {
                    $model->update([
                        'status' => SignalStatus::Closed,
                        'closed_at' => $closedAt,
                        'close_price' => $closePrice,
                        'close_reason' => $closeReason,
                    ]);
                    $updated += 1;
                }
            }
        });

        $payload = [
            'created' => $created,
            'total' => count($signals),
            'updated' => $updated,
        ];
        if ($collectStats) {
            $payload = array_merge($payload, $this->calculateSignalStats($signals));
        }

        return $payload;
    }

    private function resolveCloseReason(object $dto, ?float $closePrice): ?string
    {
        if ($closePrice === null) {
            return null;
        }

        $direction = strtoupper((string) ($dto->direction ?? ''));
        $tp = (float) $dto->takeProfit;
        $sl = (float) $dto->stopLoss;
        $epsilonTp = max(1e-8, abs($tp) * 1e-4);
        $epsilonSl = max(1e-8, abs($sl) * 1e-4);

        if ($direction === 'LONG') {
            if ($closePrice >= ($tp - $epsilonTp)) {
                return 'tp';
            }
            if ($closePrice <= ($sl + $epsilonSl)) {
                return 'sl';
            }
        }

        if ($direction === 'SHORT') {
            if ($closePrice <= ($tp + $epsilonTp)) {
                return 'tp';
            }
            if ($closePrice >= ($sl - $epsilonSl)) {
                return 'sl';
            }
        }

        return 'close';
    }

    /**
     * @param  array<int, object>  $signals
     * @return array{wins: int, losses: int, flat: int, winrate: float, sum_pct: float}
     */
    private function calculateSignalStats(array $signals): array
    {
        $wins = 0;
        $losses = 0;
        $flat = 0;
        $sumPct = 0.0;

        foreach ($signals as $signal) {
            $entry = (float) ($signal->entryPrice ?? 0.0);
            $exit = isset($signal->exitPrice) ? (float) $signal->exitPrice : null;
            $direction = (string) ($signal->direction ?? '');

            if ($entry <= 0.0 || $exit === null || ! is_finite($exit)) {
                continue;
            }

            $pct = $direction === 'SHORT'
                ? (($entry - $exit) / $entry)
                : (($exit - $entry) / $entry);

            if ($pct > 0.0) {
                $wins += 1;
            } elseif ($pct < 0.0) {
                $losses += 1;
            } else {
                $flat += 1;
            }

            $sumPct += $pct;
        }

        $total = $wins + $losses + $flat;

        return [
            'wins' => $wins,
            'losses' => $losses,
            'flat' => $flat,
            'winrate' => $total > 0 ? ($wins / $total) * 100.0 : 0.0,
            'sum_pct' => $sumPct * 100.0,
        ];
    }
}
