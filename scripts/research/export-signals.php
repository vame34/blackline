<?php

// Read-only local research export. Keep output outside git (default: private storage).
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = $argv[1] ?? storage_path('app/private/market-data/research');
if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create research output directory');
}
$signals = App\Models\Signal::orderBy('entry_at')->get([
    'id', 'symbol', 'direction', 'entry_at', 'closed_at', 'entry_price',
    'stop_loss', 'take_profit', 'close_price', 'close_reason', 'reward_risk',
]);
file_put_contents($directory.'/baseline-signals.json', $signals->toJson());
$metrics = [];
foreach ([365, 730] as $days) {
    $result = app(App\Services\Performance\TerminalBacktestService::class)->calculate(
        'portfolio', null, null, 10, 10000, $days,
    );
    unset($result['equity_curve']);
    $metrics[$days] = $result;
}
file_put_contents($directory.'/current-metrics.json', json_encode($metrics, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
echo 'Exported '.$signals->count().' signals and portfolio metrics.'.PHP_EOL;
