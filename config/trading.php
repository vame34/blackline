<?php

// Shared accounting, execution and portfolio risk; independent of the strategy adapter.
return [
    'commissionRate' => (float) env('VOLUME_STRATEGY_COMMISSION_RATE', 0.0004),
    'leverage' => [
        'min' => (int) env('VOLUME_STRATEGY_LEVERAGE_MIN', 1),
        'max' => (int) env('VOLUME_STRATEGY_LEVERAGE_MAX', 20),
    ],
    'risk_ladder' => [
        'min_percent' => (float) env('VOLUME_STRATEGY_RISK_LADDER_MIN_PERCENT', 0.5),
        'max_percent' => (float) env('VOLUME_STRATEGY_RISK_LADDER_MAX_PERCENT', 2.0),
        'step_percent' => (float) env('VOLUME_STRATEGY_RISK_LADDER_STEP_PERCENT', 0.1),
        'start_percent' => (float) env('VOLUME_STRATEGY_RISK_LADDER_START_PERCENT', 1.0),
    ],
    'portfolio_risk' => [
        'trade_percent' => 0.5,
        'total_percent' => 2.0,
        'direction_percent' => 1.5,
        'reduce_at_drawdown_percent' => 20.0,
        'halt_at_drawdown_percent' => 32.0,
        'target_drawdown_percent' => 40.0,
    ],
    'stopSlippageBps' => (float) env('VOLUME_STRATEGY_STOP_SLIPPAGE_BPS', 10.0),
    'dynamicStopSlippageEnabled' => (bool) env('VOLUME_STRATEGY_DYNAMIC_STOP_SLIPPAGE_ENABLED', true),
    'dynamicStopRangeTriggerBps' => (float) env('VOLUME_STRATEGY_DYNAMIC_STOP_RANGE_TRIGGER_BPS', 80.0),
    'dynamicStopRangeCoeff' => (float) env('VOLUME_STRATEGY_DYNAMIC_STOP_RANGE_COEFF', 0.35),
    'dynamicStopGapCoeff' => (float) env('VOLUME_STRATEGY_DYNAMIC_STOP_GAP_COEFF', 0.5),
    'dynamicStopSlippageCapBps' => (float) env('VOLUME_STRATEGY_DYNAMIC_STOP_SLIPPAGE_CAP_BPS', 120.0),
    'sameCandleExit' => 'stop',
    'spreadBps' => 0.0,
    'tradeSlippageBps' => 0.0,
];
