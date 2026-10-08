<?php

namespace Database\Seeders;

use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Models\SignalCategory;
use App\Models\User;
use App\Services\Terminal\TerminalInstrumentService;
use Illuminate\Database\Seeder;

class DashboardDemoSignalsSeeder extends Seeder
{
    public function run(): void
    {
        $recentPublished = Signal::query()
            ->where('status', SignalStatus::Published)
            ->where('published_at', '>=', now()->subDays(3))
            ->count();

        if ($recentPublished >= 8) {
            return;
        }

        $category = SignalCategory::query()->firstOrCreate(
            ['slug' => 'dashboard-demo'],
            ['name' => 'Dashboard Demo', 'sort_order' => 100],
        );

        $authorId = User::query()->value('id');
        $symbols = app(TerminalInstrumentService::class)->symbols();

        foreach (array_slice($symbols, 0, 14) as $index => $symbol) {
            $exists = Signal::query()
                ->where('symbol', $symbol)
                ->where('status', SignalStatus::Published)
                ->where('published_at', '>=', now()->subDays(7))
                ->exists();

            if ($exists) {
                continue;
            }

            $hash = abs((int) crc32($symbol));
            $base = 100 + (($hash % 5000) / 10);
            $isLong = ($hash % 2) === 0;
            $riskPct = 0.006 + (($hash % 10) / 1000);
            $rewardPct = $riskPct * (2.2 + (($hash % 6) / 10));

            $entry = round($base, 6);
            $stop = $isLong
                ? round($entry * (1 - $riskPct), 6)
                : round($entry * (1 + $riskPct), 6);
            $tp = $isLong
                ? round($entry * (1 + $rewardPct), 6)
                : round($entry * (1 - $rewardPct), 6);

            Signal::query()->create([
                'signal_category_id' => $category->id,
                'created_by' => $authorId,
                'strategy_key' => 'dashboard_demo_v1',
                'strategy_params' => ['source' => 'dashboard_demo_seeder'],
                'exchange' => 'bybit',
                'symbol' => $symbol,
                'timeframe' => '1h',
                'direction' => $isLong ? SignalDirection::Long : SignalDirection::Short,
                'entry_at' => now()->subHours(8 - min(6, $index % 7)),
                'entry_price' => $entry,
                'stop_loss' => $stop,
                'take_profit' => $tp,
                'reward_risk' => round($rewardPct / max($riskPct, 0.0001), 2),
                'status' => SignalStatus::Published,
                'published_at' => now()->subHours(7 - min(6, $index % 7)),
                'expires_at' => now()->addHours(18),
                'closed_at' => null,
                'close_reason' => null,
                'close_price' => null,
                'notes' => 'Dashboard demo signal',
                'debug' => ['seed' => 'dashboard-demo', 'hash' => $hash],
            ]);
        }
    }
}
