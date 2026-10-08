<?php

return [
    'runtime' => [
        // Align live generation with backtest by using a rolling historical window.
        'lookback_days' => (int) env('SIGNALS_RUNTIME_LOOKBACK_DAYS', 30),
        // For 1:1 parity with local Smart Volume reference, use full ranged fetch.
        'use_full_range' => (bool) env('SIGNALS_RUNTIME_USE_FULL_RANGE', true),
    ],

    'volume_strategy' => [
        // Base trading commission per side (entry/exit), fraction of notional.
        // Leverage boundaries used in terminal simulations and public API model.
        // Ladder risk model used by terminal backtest and API risk model endpoint.

        // Conservative simulation policy; neither a profit claim nor a guaranteed drawdown limit.

        // Keep defaults aligned with local trading Smart Volume crypto reference.
        'minStopPercent' => (float) env('VOLUME_STRATEGY_MIN_STOP_PERCENT', 0.005),
        'maxStopPercent' => (float) env('VOLUME_STRATEGY_MAX_STOP_PERCENT', 0.02),
        'minStopTicks' => (int) env('VOLUME_STRATEGY_MIN_STOP_TICKS', 30),
        'maxStopTicks' => (int) env('VOLUME_STRATEGY_MAX_STOP_TICKS', 0),
        'contextMinTrendStrength' => (float) env('VOLUME_STRATEGY_CONTEXT_MIN_TREND_STRENGTH', 0.0),
        'ignoreZeroVolumeCandles' => (bool) env('VOLUME_STRATEGY_IGNORE_ZERO_VOLUME_CANDLES', false),
        'entryMode' => env('VOLUME_STRATEGY_ENTRY_MODE', 'close'),
        // Dynamic stop slippage model for volatile candles (more realistic stop fills).
        'stopAtrMultiplier' => (float) env('VOLUME_STRATEGY_STOP_ATR_MULTIPLIER', 0.8),
        'rewardMultiplier' => (float) env('VOLUME_STRATEGY_REWARD_MULTIPLIER', 4.0),
        'spikeMultiplier' => (float) env('VOLUME_STRATEGY_SPIKE_MULTIPLIER', 2.0),
        'confirmVolumeMultiplier' => (float) env('VOLUME_STRATEGY_CONFIRM_VOLUME_MULTIPLIER', 1.2),
        'entryEmaTolerance' => (float) env('VOLUME_STRATEGY_ENTRY_EMA_TOLERANCE', 0.004),
        'contextPaddingDays' => (int) env('VOLUME_STRATEGY_CONTEXT_PADDING_DAYS', 15),
        'contextVolumePeriod' => (int) env('VOLUME_STRATEGY_CONTEXT_VOLUME_PERIOD', 0),
        'contextVolumeMultiplier' => (float) env('VOLUME_STRATEGY_CONTEXT_VOLUME_MULTIPLIER', 0.0),
        'deriveContextVolumePeriod' => (bool) env('VOLUME_STRATEGY_DERIVE_CONTEXT_VOLUME_PERIOD', false),
        'deriveContextVolumeMultiplier' => (bool) env('VOLUME_STRATEGY_DERIVE_CONTEXT_VOLUME_MULTIPLIER', false),
        'rsiPeriod' => (int) env('VOLUME_STRATEGY_RSI_PERIOD', 14),
        'rsiLongMin' => (float) env('VOLUME_STRATEGY_RSI_LONG_MIN', 0),
        'rsiShortMax' => (float) env('VOLUME_STRATEGY_RSI_SHORT_MAX', 100),
        'contextRsiPeriod' => (int) env('VOLUME_STRATEGY_CONTEXT_RSI_PERIOD', 14),
        'contextRsiLongMin' => (float) env('VOLUME_STRATEGY_CONTEXT_RSI_LONG_MIN', 0),
        'contextRsiShortMax' => (float) env('VOLUME_STRATEGY_CONTEXT_RSI_SHORT_MAX', 100),
        'contextAdxPeriod' => (int) env('VOLUME_STRATEGY_CONTEXT_ADX_PERIOD', 14),
        'contextMinAdx' => (float) env('VOLUME_STRATEGY_CONTEXT_MIN_ADX', 0),
        'contextRequireDiAlignment' => (bool) env('VOLUME_STRATEGY_CONTEXT_REQUIRE_DI_ALIGNMENT', false),
        'adxPeriod' => (int) env('VOLUME_STRATEGY_ADX_PERIOD', 14),
        'minAdx' => (float) env('VOLUME_STRATEGY_MIN_ADX', 0),
        'requireDiAlignment' => (bool) env('VOLUME_STRATEGY_REQUIRE_DI_ALIGNMENT', false),
        'volumeFastPeriod' => (int) env('VOLUME_STRATEGY_VOLUME_FAST_PERIOD', 20),
        'volumeSlowPeriod' => (int) env('VOLUME_STRATEGY_VOLUME_SLOW_PERIOD', 60),
        'minVolumeTrendRatio' => (float) env('VOLUME_STRATEGY_MIN_VOLUME_TREND_RATIO', 0),
        'allowLong' => (bool) env('VOLUME_STRATEGY_ALLOW_LONG', true),
        'allowShort' => (bool) env('VOLUME_STRATEGY_ALLOW_SHORT', true),
        'widgetFilter' => (bool) env('VOLUME_STRATEGY_WIDGET_FILTER', true),
        'widgetAllowRegimes' => env('VOLUME_STRATEGY_WIDGET_ALLOW_REGIMES', 'Trend,Expansion,Range,Compression'),
        'widgetMinTrendScore' => (float) env('VOLUME_STRATEGY_WIDGET_MIN_TREND_SCORE', 35),
        'widgetMinMomentumScore' => (float) env('VOLUME_STRATEGY_WIDGET_MIN_MOMENTUM_SCORE', 30),
        'widgetMaxRiskScore' => (float) env('VOLUME_STRATEGY_WIDGET_MAX_RISK_SCORE', 55),
        'widgetMinRegimeConfidence' => (float) env('VOLUME_STRATEGY_WIDGET_MIN_REGIME_CONFIDENCE', 30),
        'widgetMaxShiftRisk' => (float) env('VOLUME_STRATEGY_WIDGET_MAX_SHIFT_RISK', 60),
        'widgetMinTrendHealth' => (float) env('VOLUME_STRATEGY_WIDGET_MIN_TREND_HEALTH', 0),
        'widgetMaxMeanReversionEdge' => (float) env('VOLUME_STRATEGY_WIDGET_MAX_MEAN_REVERSION_EDGE', 100),
    ],

    'symbol_overrides' => (static function (): array {
        $defaults = [
            'RUNEUSDT' => ['stopAtrMultiplier' => 1.2, 'rewardMultiplier' => 4.5, 'widgetFilter' => false],
            'BTCUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'AVAXUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'TRXUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.5, 'widgetFilter' => false],
            'XRPUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'SEIUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'BNBUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'MANAUSDT' => ['stopAtrMultiplier' => 0.4, 'rewardMultiplier' => 4.5, 'widgetFilter' => true],
            'ALGOUSDT' => ['stopAtrMultiplier' => 0.7, 'rewardMultiplier' => 4.5, 'widgetFilter' => true],
            'XLMUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'LTCUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'COMPUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'DOGEUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'KAVAUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'SANDUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'ICPUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'DOTUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'ATOMUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'ETHUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'ZRXUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'FILUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'CHZUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => false],
            'SUSHIUSDT' => ['stopAtrMultiplier' => 1.0, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'INJUSDT' => ['stopAtrMultiplier' => 0.6, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
            'SNXUSDT' => ['stopAtrMultiplier' => 0.8, 'rewardMultiplier' => 4.0, 'widgetFilter' => true],
        ];

        $raw = env('VOLUME_STRATEGY_SYMBOL_OVERRIDES', '{}');
        if (! is_string($raw) || trim($raw) === '') {
            return $defaults;
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return $defaults;
        }

        $result = $defaults;
        foreach ($decoded as $symbol => $overrides) {
            if (! is_string($symbol) || ! is_array($overrides)) {
                continue;
            }
            $result[strtoupper($symbol)] = $overrides;
        }

        return $result;
    })(),
];
