<?php

namespace App\Services\Performance;

use Illuminate\Http\Request;

class TerminalPerformanceInputService
{
    public const ALL_SYMBOL = '__ALL__';

    /**
     * @return array<int, string>
     */
    public function extractSymbols(Request $request, string $fallbackSymbol = ''): array
    {
        $symbolsQuery = $request->query('symbols', []);
        if (is_array($symbolsQuery)) {
            return array_values(array_filter(array_map('strval', $symbolsQuery), static fn (string $value) => $value !== ''));
        }

        if (is_string($symbolsQuery) && trim($symbolsQuery) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $symbolsQuery))));
        }

        return $fallbackSymbol !== '' ? [$fallbackSymbol] : [];
    }

    /**
     * @param array<int, string> $symbolOptions
     * @param array<int, string> $symbols
     * @return array<int, string>
     */
    public function resolveSelectedSymbols(array $symbolOptions, string $symbol, array $symbols): array
    {
        $selectedSymbols = array_values(array_intersect($symbols, array_merge([self::ALL_SYMBOL], $symbolOptions)));
        if ($selectedSymbols === [] && $symbol !== '' && in_array($symbol, $symbolOptions, true)) {
            return [$symbol];
        }

        return $selectedSymbols !== [] ? $selectedSymbols : [self::ALL_SYMBOL];
    }

    /**
     * @param array<int, string> $selectedSymbols
     * @return array{0:string,1:?string}
     */
    public function resolveScope(array $selectedSymbols): array
    {
        if ($selectedSymbols === [self::ALL_SYMBOL]) {
            return ['portfolio', null];
        }

        if (count($selectedSymbols) === 1) {
            return ['symbol', $selectedSymbols[0]];
        }

        return ['symbols', null];
    }

    public function normalizeCapital(string $capital): float
    {
        return $this->normalizeNumber($capital, 100.0, 1_000_000_000.0, 10_000.0);
    }

    public function normalizeLeverage(string $leverage): int
    {
        return (int) round($this->normalizeNumber($leverage, 1.0, 20.0, 10.0));
    }

    public function normalizePeriod(string $period): int
    {
        $value = (int) $this->normalizeNumber($period, 7.0, 730.0, 365.0);
        $allowed = [7, 14, 30, 60, 90, 180, 365, 730];

        return in_array($value, $allowed, true) ? $value : 365;
    }

    /**
     * @return array<string, string>
     */
    public function coinIconMap(): array
    {
        return [
            'BTC' => asset('images/coins/btc.png'),
            'SOL' => asset('images/coins/sol.png'),
            'ADA' => asset('images/coins/ada.png'),
            'LINK' => asset('images/coins/link.png'),
            'BCH' => asset('images/coins/bch.png'),
            'BNB' => asset('images/coins/bnb.png'),
            'TRX' => asset('images/coins/trx.png'),
            'RUNE' => asset('images/coins/rune.png'),
            'MANA' => asset('images/coins/mana.png'),
            'XRP' => asset('images/coins/xrp.png'),
            'XLM' => asset('images/coins/xlm.png'),
            'AVAX' => asset('images/coins/avax.png'),
            'LTC' => asset('images/coins/ltc.png'),
            'SAND' => asset('images/coins/sand.png'),
            'DOGE' => asset('images/coins/doge.png'),
            'DOT' => asset('images/coins/dot.png'),
            'ATOM' => asset('images/coins/atom.png'),
            'FIL' => asset('images/coins/fil.png'),
            'ETH' => asset('images/coins/eth.png'),
            'PEPE' => asset('images/coins/pepe.jpg'),
            'INJ' => asset('images/coins/inj.png'),
            'APT' => asset('images/coins/apt.png'),
            'ALGO' => asset('images/coins/algo.png'),
            'COMP' => asset('images/coins/comp.png'),
            'KAVA' => asset('images/coins/kava.png'),
            'ICP' => asset('images/coins/icp.png'),
            'ZRX' => asset('images/coins/zrx.png'),
            'CHZ' => asset('images/coins/chz.png'),
            'SUSHI' => asset('images/coins/sushi.png'),
            'SEI' => asset('images/coins/sei.png'),
            'SNX' => asset('images/coins/snx.png'),
        ];
    }

    private function normalizeNumber(string $raw, float $min, float $max, float $default): float
    {
        $normalized = str_replace(',', '.', trim($raw));
        if (! is_numeric($normalized)) {
            return $default;
        }

        return max($min, min($max, (float) $normalized));
    }
}
