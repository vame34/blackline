<?php

namespace App\Livewire\Signals;

use App\DTOs\Signals\SignalListFilters;
use App\Services\Analytics\BlacklineAnalyticsService;
use App\Services\Preferences\DisplayTimezone;
use App\Services\Signals\SignalFilterPresetService;
use App\Services\Signals\SignalService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    private const SYMBOL_ALL = '__ALL__';

    /**
     * Single source of truth for available analytics timeframe codes.
     *
     * @var array<int, string>
     */
    private const ANALYTICS_TIMEFRAMES = ['1', '5', '15', '60', '240', '1D'];

    public function getListeners(): array
    {
        return [
            'display-timezone-synchronized' => '$refresh',
            'echo-private:signals.subscribers,signal.published' => 'handleSignalPublished',
            'echo-private:signals.subscribers,signal.closed' => 'handleSignalClosed',
        ];
    }

    public bool $newSignalAvailable = false;

    public ?int $newSignalId = null;

    public ?string $newSignalSymbol = null;

    #[Url]
    public int $perPage = 20;

    #[Url]
    public string $symbol = '';

    #[Url]
    public string $strategy = '';

    #[Url]
    public string $status = 'all';

    #[Url]
    public string $direction = '';

    #[Url]
    public string $risk = '';

    #[Url]
    public string $sort = 'recent';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    #[Url]
    public string $outcome = 'all';

    #[Url]
    public string $viewMode = 'analysis';

    #[Url]
    public string $listDensity = 'detailed';

    #[Url]
    public string $searchSymbol = '';

    #[Url]
    public string $analyticsTimeframe = '60';

    public string $timeframe = '';

    public string $contextTimeframe = '';

    public string $exchange = '';

    public int $chartKey = 0;

    public bool $autoScroll = true;

    public string $presetName = '';

    public ?int $activePresetId = null;

    public ?string $feedNotice = null;

    /**
     * @var array<int, int>
     */
    public array $highlightedSignalIds = [];

    public function mount(TerminalInstrumentService $instruments): void
    {
        $registry = app(StrategyRegistry::class);
        $this->strategy = $this->strategy ?: $registry->activeKey();
        abort_unless(array_key_exists($this->strategy, $registry->options()), 422);
        $this->exchange = (string) config('terminal.exchange', 'bybit');
        $this->timeframe = (string) config('terminal.timeframe', '1h');
        $this->contextTimeframe = (string) config('terminal.context_timeframe', '4h');
        $this->analyticsTimeframe = $this->normalizeAnalyticsTimeframe($this->analyticsTimeframe);

        $symbols = $instruments->symbols();
        $selected = strtoupper($this->symbol);
        if ($selected === self::SYMBOL_ALL) {
            $this->symbol = self::SYMBOL_ALL;
        } elseif ($selected === '' || ! in_array($selected, $symbols, true)) {
            $this->symbol = $instruments->defaultSymbol();
        } else {
            $this->symbol = $selected;
        }
    }

    public function updatingStrategy(string $value): void
    {
        abort_unless(array_key_exists($value, app(StrategyRegistry::class)->options()), 422);
        $this->activePresetId = null;
        $this->highlightedSignalIds = [];
        $this->newSignalAvailable = false;
        $this->resetPage();
    }

    public function updatingSymbol(): void
    {
        $this->resetPage();
    }

    public function selectSymbol(string $symbol): void
    {
        $normalized = strtoupper(trim($symbol));
        if ($normalized === '') {
            return;
        }

        $this->symbol = $normalized === self::SYMBOL_ALL ? self::SYMBOL_ALL : $normalized;
    }

    public function updatingStatus(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatedStatus(string $value): void
    {
        if ($value !== 'closed' && in_array($this->sort, ['best_r', 'worst_r'], true)) {
            $this->sort = 'recent';
        }
    }

    public function updatingDirection(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatingRisk(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatedSort(string $value): void
    {
        if (in_array($value, ['best_r', 'worst_r'], true)) {
            $this->status = 'closed';
        }
    }

    public function updatingDateFrom(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatingOutcome(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatedOutcome(string $value): void
    {
        if (in_array($value, ['win', 'loss'], true) && $this->status !== 'closed') {
            $this->status = 'closed';
        }
    }

    public function updatingSearchSymbol(): void
    {
        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatedSymbol(string $value): void
    {
        $normalized = strtoupper($value);
        if ($value !== $normalized) {
            $this->symbol = $normalized;

            return;
        }

        $this->newSignalAvailable = false;
        $this->newSignalId = null;
        $this->newSignalSymbol = null;
        $this->chartKey += 1;
        $this->resetPage();
    }

    public function updatedViewMode(string $value): void
    {
        if ($value === 'scanner') {
            $this->listDensity = 'compact';
            $this->dismissNewSignal();
            $this->highlightedSignalIds = [];
        }

        $this->activePresetId = null;
        $this->resetPage();
    }

    public function updatedListDensity(string $value): void
    {
        if ($this->viewMode === 'scanner' && $value !== 'compact') {
            $this->listDensity = 'compact';
        }

        $this->activePresetId = null;
    }

    public function updatedAnalyticsTimeframe(string $value): void
    {
        $this->analyticsTimeframe = $this->normalizeAnalyticsTimeframe($value);
    }

    public function resetFilters(): void
    {
        $this->status = 'all';
        $this->direction = '';
        $this->risk = '';
        $this->sort = 'recent';
        $this->dateFrom = '';
        $this->dateTo = '';
        $this->outcome = 'all';
        $this->searchSymbol = '';
        $this->activePresetId = null;
        $this->resetPage();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleSignalPublished(array $payload): void
    {
        if (($payload['strategy_key'] ?? $this->strategy) !== $this->strategy) {
            return;
        }

        $payloadId = isset($payload['id']) ? (int) $payload['id'] : null;
        $payloadSymbol = isset($payload['symbol']) ? (string) $payload['symbol'] : null;
        $payloadSymbolUpper = $payloadSymbol ? strtoupper($payloadSymbol) : null;

        if ($this->viewMode === 'scanner') {
            if ($payloadId) {
                $ids = array_values(array_unique(array_merge([$payloadId], $this->highlightedSignalIds)));
                $this->highlightedSignalIds = array_slice($ids, 0, 20);
            }

            $this->newSignalAvailable = false;
            $this->newSignalId = null;
            $this->newSignalSymbol = null;
            $this->resetPage();
            if ($this->autoScroll) {
                $this->dispatch('terminal-feed:scroll-top');
            }

            return;
        }

        $this->newSignalAvailable = true;
        $this->newSignalId = $payloadId;
        $this->newSignalSymbol = $payloadSymbol;

        if ($payloadSymbolUpper && $payloadSymbolUpper === $this->symbol) {
            $this->chartKey += 1;
        }

        if ($this->status !== 'closed' && ($payloadSymbolUpper === $this->symbol || $this->symbol === '' || $this->symbol === self::SYMBOL_ALL)) {
            $this->resetPage();
            if ($this->autoScroll) {
                $this->dispatch('terminal-feed:scroll-top');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleSignalClosed(array $payload): void
    {
        if (($payload['strategy_key'] ?? $this->strategy) !== $this->strategy) {
            return;
        }

        $payloadId = isset($payload['id']) ? (int) $payload['id'] : null;
        $payloadSymbol = isset($payload['symbol']) ? (string) $payload['symbol'] : null;
        $payloadSymbolUpper = $payloadSymbol ? strtoupper($payloadSymbol) : null;

        if ($this->viewMode === 'scanner') {
            if ($payloadId) {
                $ids = array_values(array_unique(array_merge([$payloadId], $this->highlightedSignalIds)));
                $this->highlightedSignalIds = array_slice($ids, 0, 20);
            }

            $this->resetPage();
            if ($this->autoScroll) {
                $this->dispatch('terminal-feed:scroll-top');
            }

            return;
        }

        if ($this->symbol === self::SYMBOL_ALL || ($payloadSymbolUpper && $payloadSymbolUpper === $this->symbol)) {
            $this->chartKey += 1;
            $this->resetPage();
            if ($this->autoScroll) {
                $this->dispatch('terminal-feed:scroll-top');
            }
        }
    }

    public function dismissNewSignal(): void
    {
        $this->newSignalAvailable = false;
        $this->newSignalId = null;
        $this->newSignalSymbol = null;
    }

    public function dismissNotice(): void
    {
        $this->feedNotice = null;
    }

    public function savePreset(SignalFilterPresetService $presets): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $this->validate([
            'presetName' => ['required', 'string', 'max:80'],
        ]);

        $preset = $presets->create($userId, [
            'name' => $this->presetName,
            'filters' => $this->currentPresetFilters(),
        ]);

        $this->activePresetId = $preset->id;
        $this->presetName = '';
        $this->resetErrorBag('presetName');
        $this->feedNotice = __('ui.signals.terminal_preset_saved');
    }

    public function applyPreset(SignalFilterPresetService $presets, int $presetId): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $preset = $presets->find($userId, $presetId);
        if (! $preset) {
            return;
        }

        $filters = (array) ($preset->filters ?? []);
        $presetStrategy = (string) ($filters['strategy'] ?? $this->strategy);
        if (array_key_exists($presetStrategy, app(StrategyRegistry::class)->options())) {
            $this->strategy = $presetStrategy;
        }
        $this->status = (string) ($filters['status'] ?? '');
        $this->direction = (string) ($filters['direction'] ?? '');
        $this->risk = (string) ($filters['risk'] ?? '');
        $this->sort = (string) ($filters['sort'] ?? 'recent');
        $this->dateFrom = (string) ($filters['date_from'] ?? '');
        $this->dateTo = (string) ($filters['date_to'] ?? '');
        $this->outcome = (string) ($filters['outcome'] ?? 'all');
        $this->searchSymbol = (string) ($filters['search'] ?? '');
        $this->viewMode = (string) ($filters['view_mode'] ?? 'analysis');
        $this->listDensity = (string) ($filters['list_density'] ?? 'detailed');
        $this->autoScroll = (bool) ($filters['auto_scroll'] ?? true);

        if (in_array($this->sort, ['best_r', 'worst_r'], true)) {
            $this->status = 'closed';
        }

        $this->activePresetId = $preset->id;
        $this->resetPage();
    }

    public function deletePreset(SignalFilterPresetService $presets, int $presetId): void
    {
        $userId = auth()->id();
        if (! $userId) {
            return;
        }

        $presets->delete($userId, $presetId);

        if ($this->activePresetId === $presetId) {
            $this->activePresetId = null;
        }
    }

    private function resolveFeedSymbol(): ?string
    {
        $search = strtoupper(trim($this->searchSymbol));

        if ($this->viewMode === 'scanner') {
            return $search !== '' ? $search : null;
        }

        if ($this->symbol === '' || $this->symbol === self::SYMBOL_ALL) {
            return null;
        }

        return $this->symbol;
    }

    /**
     * @return array<string, mixed>
     */
    private function currentPresetFilters(): array
    {
        return [
            'strategy' => $this->strategy,
            'status' => $this->status,
            'direction' => $this->direction,
            'risk' => $this->risk,
            'sort' => $this->sort,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'outcome' => $this->outcome,
            'search' => $this->searchSymbol,
            'view_mode' => $this->viewMode,
            'list_density' => $this->listDensity,
            'auto_scroll' => $this->autoScroll,
        ];
    }

    public function render(
        SignalService $signals,
        TerminalInstrumentService $instruments,
        SignalFilterPresetService $presets,
        BlacklineAnalyticsService $analytics,
    ): View {
        $instrumentOptions = $instruments->list();
        $selectedInstrument = null;
        foreach ($instrumentOptions as $option) {
            if ($option['symbol'] === $this->symbol) {
                $selectedInstrument = $option;
                break;
            }
        }

        $analysisSymbol = $this->symbol === self::SYMBOL_ALL
            ? $instruments->defaultSymbol()
            : $this->symbol;

        $analysis = $analytics->analyze(
            symbol: $analysisSymbol,
            interval: $this->providerInterval($this->analyticsTimeframe),
        );

        $feedFilters = new SignalListFilters(
            strategyKey: $this->strategy,
            symbol: $this->resolveFeedSymbol(),
            timeframe: $this->timeframe,
            status: $this->status !== '' ? $this->status : null,
            direction: $this->direction !== '' ? $this->direction : null,
            risk: $this->risk !== '' ? $this->risk : null,
            sort: $this->sort !== '' ? $this->sort : null,
            timezone: app(DisplayTimezone::class)->resolved(),
            dateFrom: $this->dateFrom !== '' ? $this->dateFrom : null,
            dateTo: $this->dateTo !== '' ? $this->dateTo : null,
            outcome: $this->outcome !== '' ? $this->outcome : null,
        );

        $yearSummary = $signals->summarizeClosedPerformance($feedFilters, 365);

        return view('livewire.signals.index', [
            'strategyOptions' => app(StrategyRegistry::class)->options(),
            'signals' => $signals->paginatePublishedForCabinet($feedFilters, $this->perPage),
            'instrumentOptions' => $instrumentOptions,
            'selectedInstrument' => $selectedInstrument,
            'analysisSymbol' => $analysisSymbol,
            'symbolAllValue' => self::SYMBOL_ALL,
            'presets' => $presets->listForUser((int) auth()->id()),
            'analyticsTimeframes' => self::ANALYTICS_TIMEFRAMES,
            'analyticsTimeframeLabels' => $this->analyticsTimeframeLabels(),
            'analyticsTvInterval' => $this->tradingViewInterval($this->analyticsTimeframe),
            'blackline' => $analysis['blackline'],
            'regime' => $analysis['regime'],
            'customIndicators' => $analysis['customIndicators'],
            'yearWinRate' => $yearSummary['win_rate'],
            'yearSumPct' => $yearSummary['sum_pct'],
            'yearTotalTrades' => $yearSummary['total_trades'],
        ])->layout('layouts.terminal');
    }

    private function providerInterval(string $timeframe): string
    {
        $normalized = $this->normalizeAnalyticsTimeframe($timeframe);

        return match ($normalized) {
            '1' => '1m',
            '5' => '5m',
            '15' => '15m',
            '60' => '1h',
            '240' => '4h',
            '1D' => '1d',
            default => '1h',
        };
    }

    private function tradingViewInterval(string $timeframe): string
    {
        return $this->normalizeAnalyticsTimeframe($timeframe);
    }

    private function normalizeAnalyticsTimeframe(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return in_array($normalized, self::ANALYTICS_TIMEFRAMES, true) ? $normalized : '60';
    }

    /**
     * @return array<string, string>
     */
    private function analyticsTimeframeLabels(): array
    {
        $labels = [];
        foreach (self::ANALYTICS_TIMEFRAMES as $code) {
            $labels[$code] = match ($code) {
                '60' => '1h',
                '240' => '4h',
                '1D' => '1D',
                default => strtolower($code).'m',
            };
        }

        return $labels;
    }
}
