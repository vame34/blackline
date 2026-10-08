<?php

namespace App\Http\Controllers\Api\V1;

use App\DTOs\Signals\SignalListFilters;
use App\Enums\SignalStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SignalResource;
use App\Models\Signal;
use App\Repositories\Signals\SignalRepository;
use App\Services\Api\RiskManagementProfileService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class SignalController extends Controller
{
    public function __construct(
        private readonly SignalRepository $signals,
        private readonly TerminalInstrumentService $instruments,
        private readonly RiskManagementProfileService $riskManagement,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'strategy' => ['nullable', Rule::in(array_keys(app(StrategyRegistry::class)->options()))],
            'symbol' => ['nullable', 'string', 'max:32'],
            'direction' => ['nullable', 'in:LONG,SHORT'],
            'timeframe' => ['nullable', 'string', 'max:12'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:published,closed,all'],
            'risk' => ['nullable', 'in:low,medium,high'],
            'sort' => ['nullable', 'in:recent,oldest,entry_new,entry_old,best_r,worst_r'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'period' => ['nullable', 'in:1d,7d,14d,30d,60d,90d,180d,365d,730d,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        [$dateFrom, $dateTo] = $this->resolveDateRange(
            $validated['period'] ?? null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        );

        $defaultPerPage = max(1, (int) config('api.limits.default_per_page', 20));
        $maxPerPage = max($defaultPerPage, (int) config('api.limits.max_per_page', 100));
        $perPage = min((int) ($validated['per_page'] ?? $defaultPerPage), $maxPerPage);

        $paginator = $this->signals->paginatePublished(
            new SignalListFilters(
                strategyKey: $validated['strategy'] ?? null,
                symbol: $validated['symbol'] ?? null,
                direction: $validated['direction'] ?? null,
                timeframe: $validated['timeframe'] ?? null,
                categoryId: $validated['category_id'] ?? null,
                status: $validated['status'] ?? null,
                risk: $validated['risk'] ?? null,
                sort: $validated['sort'] ?? 'recent',
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            ),
            $perPage,
        );

        return response()->json([
            'data' => SignalResource::collection($paginator->getCollection())->resolve(),
            'money_management' => $this->riskManagement->realtimeSummary(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $includeUpdates = filter_var($request->query('include_updates', true), FILTER_VALIDATE_BOOL);
        $now = now();

        $query = Signal::query()
            ->with('category')
            ->whereKey($id)
            ->whereIn('status', [SignalStatus::Published, SignalStatus::Closed])
            ->where('published_at', '<=', $now)
            ->where(function ($builder) use ($now): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        if ($includeUpdates) {
            $query->with(['updates' => fn ($builder) => $builder->orderByDesc('published_at')->limit(20)]);
        }

        $signal = $query->first();
        if (! $signal) {
            return response()->json([
                'message' => 'Signal not found.',
                'error_code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'data' => SignalResource::make($signal)->resolve(),
            'money_management' => $this->riskManagement->realtimeSummary(),
        ]);
    }

    public function stream(Request $request): Response
    {
        $validated = $request->validate([
            'since_id' => ['nullable', 'integer', 'min:0'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:300'],
            'poll_ms' => ['nullable', 'integer', 'min:500', 'max:5000'],
        ]);

        $lastId = (int) ($validated['since_id'] ?? 0);
        $maxDuration = min(
            (int) ($validated['timeout'] ?? config('api.stream.max_duration_seconds', 55)),
            max(5, (int) config('api.stream.max_client_timeout_seconds', 60)),
        );
        $pollMs = (int) ($validated['poll_ms'] ?? config('api.stream.poll_interval_ms', 1200));
        $heartbeatSeconds = max(5, (int) config('api.stream.heartbeat_seconds', 15));
        $batchLimit = max(1, (int) config('api.stream.batch_limit', 25));
        $tokenId = (string) $request->attributes->get('api_token_id', '');

        if ($tokenId === '') {
            return response()->json([
                'message' => 'Missing API token context.',
                'error_code' => 'UNAUTHORIZED',
            ], 401);
        }

        $maxStreamsPerToken = max(1, (int) config('api.stream.max_concurrent_per_token', 2));
        $streamCounterKey = 'api:signals:stream:token:'.$tokenId;
        $counterTtl = now()->addSeconds($maxDuration + 30);

        if (! Cache::add($streamCounterKey, 1, $counterTtl)) {
            $currentStreams = (int) Cache::increment($streamCounterKey);
            Cache::put($streamCounterKey, $currentStreams, $counterTtl);

            if ($currentStreams > $maxStreamsPerToken) {
                Cache::decrement($streamCounterKey);

                return response()->json([
                    'message' => 'Too many concurrent stream connections for this token.',
                    'error_code' => 'TOO_MANY_STREAMS',
                ], 429);
            }
        }

        $response = response()->stream(function () use ($lastId, $maxDuration, $pollMs, $heartbeatSeconds, $batchLimit, $streamCounterKey): void {
            try {
                ignore_user_abort(true);
                set_time_limit(0);

                $cursor = $lastId;
                $startedAt = time();
                $lastHeartbeatAt = time();

                $this->emitSse('ready', [
                    'cursor' => $cursor,
                    'server_time' => now()->toISOString(),
                    'money_management' => $this->riskManagement->realtimeSummary(),
                ]);

                while (! connection_aborted() && (time() - $startedAt) < $maxDuration) {
                    $published = $this->queryPublishedSignalsBatch($cursor, $batchLimit);

                    if ($published->isNotEmpty()) {
                        foreach ($published as $signal) {
                            $cursor = max($cursor, (int) $signal->id);

                            $this->emitSse('signal.published', [
                                'cursor' => $cursor,
                                'signal' => SignalResource::make($signal)->resolve(),
                                'money_management' => $this->riskManagement->realtimeSummary(),
                            ]);
                        }

                        $lastHeartbeatAt = time();

                        continue;
                    }

                    if ((time() - $lastHeartbeatAt) >= $heartbeatSeconds) {
                        $this->emitSse('heartbeat', [
                            'cursor' => $cursor,
                            'server_time' => now()->toISOString(),
                        ]);
                        $lastHeartbeatAt = time();
                    }

                    usleep($pollMs * 1000);
                }

                $this->emitSse('end', [
                    'cursor' => $cursor,
                    'reason' => 'reconnect_required',
                    'server_time' => now()->toISOString(),
                ]);
            } finally {
                $current = (int) Cache::decrement($streamCounterKey);
                if ($current <= 0) {
                    Cache::forget($streamCounterKey);
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);

        return $response;
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->attributes->get('api_token');

        return response()->json([
            'data' => [
                'id' => $user?->id,
                'name' => $user?->name,
                'email' => $user?->email,
                'token' => [
                    'id' => $token?->id,
                    'name' => $token?->name,
                    'last_used_at' => optional($token?->last_used_at)->toISOString(),
                    'expires_at' => optional($token?->expires_at)->toISOString(),
                ],
            ],
        ]);
    }

    public function instruments(): JsonResponse
    {
        return response()->json([
            'data' => $this->instruments->list(),
        ]);
    }

    public function riskModel(): JsonResponse
    {
        return response()->json([
            'data' => $this->riskManagement->fullModel(),
        ]);
    }

    public function riskModelVersion(): JsonResponse
    {
        return response()->json([
            'data' => [
                'model' => 'terminal_backtest_ladder',
                'version' => $this->riskManagement->version(),
            ],
        ]);
    }

    /**
     * @return Collection<int, Signal>
     */
    private function queryPublishedSignalsBatch(int $cursor, int $limit)
    {
        $now = now();

        return Signal::query()
            ->with('category')
            ->where('id', '>', $cursor)
            ->whereIn('status', [SignalStatus::Published, SignalStatus::Closed])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', $now)
            ->where(function ($builder) use ($now): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function emitSse(string $event, array $payload): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n";

        if (function_exists('ob_flush')) {
            @ob_flush();
        }

        flush();
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function resolveDateRange(?string $period, ?string $dateFrom, ?string $dateTo): array
    {
        if ($dateFrom !== null || $dateTo !== null) {
            return [$dateFrom, $dateTo];
        }

        if ($period === null || $period === '' || $period === 'all') {
            return [null, null];
        }

        $days = (int) rtrim($period, 'd');
        if ($days <= 0) {
            return [null, null];
        }

        return [
            Carbon::now()->subDays($days)->toDateString(),
            Carbon::now()->toDateString(),
        ];
    }
}
