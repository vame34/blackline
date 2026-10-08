<?php

namespace App\Services\Trading\Adapters;

use App\Contracts\Trading\StrategyAdapter;
use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\StrategyResult;
use App\DTOs\Trading\StrategySignal;
use App\DTOs\Trading\VolumeSignal;
use App\Services\Trading\Strategies\VolumeStrategy;
use App\Services\Trading\Strategies\VolumeStrategyConfig;

final class SmartVolumeAdapter implements StrategyAdapter
{
    public function generate(InstrumentData $data, array $configuration, bool $simulateExits = false): StrategyResult
    {
        $baseConfig = array_replace($configuration, (array) config('trading', []));
        $deriveContextVolumePeriod = (bool) ($baseConfig['deriveContextVolumePeriod'] ?? false);
        $deriveContextVolumeMultiplier = (bool) ($baseConfig['deriveContextVolumeMultiplier'] ?? false);
        if ($deriveContextVolumeMultiplier) {
            throw new \InvalidArgumentException('Full-window volume calibration is not causal; use a fixed contextVolumeMultiplier.');
        }
        $derivedContextVolumePeriod = $this->deriveContextVolumePeriod(
            $data->contextCandles,
            (int) ($baseConfig['contextPaddingDays'] ?? 30),
        );
        $forcedContextVolumePeriod = (int) ($baseConfig['contextVolumePeriod'] ?? 0);
        $resolvedContextVolumePeriod = $forcedContextVolumePeriod > 0
            ? $forcedContextVolumePeriod
            : ($deriveContextVolumePeriod ? $derivedContextVolumePeriod : 0);

        $forcedContextVolumeMultiplier = (float) ($baseConfig['contextVolumeMultiplier'] ?? 0.0);
        $resolvedContextVolumeMultiplier = $forcedContextVolumeMultiplier > 0
            ? $forcedContextVolumeMultiplier
            : 0.0;

        $strategyConfig = VolumeStrategyConfig::fromArray(array_merge($baseConfig, [
            'contextVolumePeriod' => $resolvedContextVolumePeriod,
            'contextVolumeMultiplier' => $resolvedContextVolumeMultiplier,
        ]));
        $strategy = new VolumeStrategy($strategyConfig);
        $data = new InstrumentData(
            symbol: $data->symbol, exchange: $data->exchange, interval: $data->interval,
            candles: $data->candles, contextCandles: $data->contextCandles,
            contextVolumeMultiplier: $resolvedContextVolumeMultiplier,
            priceStep: $data->priceStep, lotSize: $data->lotSize,
        );
        $result = $simulateExits ? $strategy->generateSignals($data, debug: true) : $strategy->generateEntrySignals($data, debug: true);
        $signals = array_map(static fn (VolumeSignal $signal): StrategySignal => new StrategySignal(
            symbol: $signal->symbol, exchange: $signal->exchange, direction: $signal->direction,
            entryTimeMs: $signal->entryTimeMs, entryPrice: $signal->entryPrice,
            stopLoss: $signal->stopLoss, takeProfit: $signal->takeProfit, rewardRisk: $signal->rewardRisk,
            exitTimeMs: $signal->exitTimeMs, exitPrice: $signal->exitPrice,
            metadata: ['level' => $signal->level],
        ), $result['signals'] ?? []);

        return new StrategyResult($signals, $result['debug'] ?? []);

    }

    private function deriveContextVolumePeriod(array $contextCandles, int $paddingDays = 15): int
    {
        if (count($contextCandles) < 2) {
            return 20;
        }

        $intervalMs = max(0, $contextCandles[1]->openTimeMs - $contextCandles[0]->openTimeMs);
        if ($intervalMs <= 0) {
            return 20;
        }

        $candlesPerDay = (24 * 60 * 60 * 1000) / max(1, $intervalMs);
        $targetDays = max(1, min(30, $paddingDays ?: 15));
        $period = (int) round($candlesPerDay * $targetDays * 0.25);

        return max(5, $period ?: 5);
    }
}
