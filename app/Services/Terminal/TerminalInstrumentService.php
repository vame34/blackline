<?php

namespace App\Services\Terminal;

final class TerminalInstrumentService
{
    /**
     * @return array<int, array{symbol: string, label: string, tradingview: string, exchange: string}>
     */
    public function list(): array
    {
        $exchange = (string) config('terminal.exchange', 'bybit');
        $items = (array) config('terminal.instruments', []);

        return array_values(array_map(
            static function (array $item) use ($exchange): array {
                $symbol = strtoupper((string) ($item['symbol'] ?? ''));
                $label = (string) ($item['label'] ?? $symbol);

                return [
                    'symbol' => $symbol,
                    'label' => $label,
                    'tradingview' => (string) ($item['tradingview'] ?? strtoupper($exchange).':'.$symbol),
                    'exchange' => (string) ($item['exchange'] ?? $exchange),
                ];
            },
            $items,
        ));
    }

    public function defaultSymbol(): string
    {
        return strtoupper((string) config('terminal.default_symbol', 'BTCUSDT'));
    }

    /**
     * @return array<int, string>
     */
    public function symbols(): array
    {
        return array_map(
            static fn (array $item) => $item['symbol'],
            $this->list(),
        );
    }
}
