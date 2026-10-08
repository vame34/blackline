<?php

namespace App\Console\Commands;

use App\Services\Signals\SignalAutoCloseService;
use Illuminate\Console\Command;

class CloseActiveSignalsCommand extends Command
{
    protected $signature = 'signals:close-active
        {--symbol= : Single symbol, e.g. BTCUSDT}
        {--symbols= : Comma-separated symbols list}
        {--timeframe= : Optional timeframe filter}
        {--provider= : Optional provider override}
        {--fallbacks= : Comma-separated fallback providers}
        {--exchange= : Optional exchange filter}
        {--dry-run : Do not write to DB}';

    protected $description = 'Close active published signals when TP/SL conditions are met.';

    public function handle(SignalAutoCloseService $service): int
    {
        $result = $service->closeActiveSignals(
            symbols: $this->resolveSymbols(),
            timeframe: $this->resolveStringOption('timeframe'),
            provider: $this->resolveStringOption('provider'),
            fallbacks: $this->parseFallbacks($this->option('fallbacks')),
            exchange: $this->resolveStringOption('exchange'),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->info(
            "Close pass complete. Checked: {$result['checked']}, closed: {$result['closed']}, no candles: {$result['skipped_no_candles']}"
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>|null
     */
    private function resolveSymbols(): ?array
    {
        $single = $this->option('symbol');
        if (is_string($single) && trim($single) !== '') {
            return [strtoupper(trim($single))];
        }

        $list = $this->option('symbols');
        if (is_string($list) && trim($list) !== '') {
            $items = array_map('trim', explode(',', $list));
            $items = array_values(array_filter($items, static fn (string $item) => $item !== ''));

            return array_map('strtoupper', $items);
        }

        return null;
    }

    private function resolveStringOption(string $name): ?string
    {
        $value = $this->option($name);
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }

    /**
     * @return array<int, string>|null
     */
    private function parseFallbacks(mixed $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_values(array_filter($items, static fn (string $item) => $item !== ''));

        return $items !== [] ? $items : null;
    }
}
