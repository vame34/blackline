<?php

use App\Services\Trading\Adapters\SmartVolumeAdapter;

return [
    'active' => env('STRATEGY_ACTIVE', 'smart_volume_v1'),
    'adapters' => [
        'smart_volume_v1' => [
            'label' => 'Smart Volume',
            'adapter' => SmartVolumeAdapter::class,
            'enabled' => true,
            'config' => 'signals.volume_strategy',
            'symbol_overrides' => 'signals.symbol_overrides',
        ],
    ],
];
