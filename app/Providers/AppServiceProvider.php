<?php

namespace App\Providers;

use App\Models\Signal;
use App\Observers\SignalObserver;
use App\Repositories\Performance\EloquentPerformanceSnapshotRepository;
use App\Repositories\Performance\PerformanceSnapshotRepository;
use App\Repositories\Signals\EloquentSignalBackfillBatchRepository;
use App\Repositories\Signals\EloquentSignalFilterPresetRepository;
use App\Repositories\Signals\EloquentSignalRepository;
use App\Repositories\Signals\EloquentSignalUpdateRepository;
use App\Repositories\Signals\SignalBackfillBatchRepository;
use App\Repositories\Signals\SignalFilterPresetRepository;
use App\Repositories\Signals\SignalRepository;
use App\Repositories\Signals\SignalUpdateRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SignalRepository::class, EloquentSignalRepository::class);
        $this->app->bind(SignalBackfillBatchRepository::class, EloquentSignalBackfillBatchRepository::class);
        $this->app->bind(SignalFilterPresetRepository::class, EloquentSignalFilterPresetRepository::class);
        $this->app->bind(SignalUpdateRepository::class, EloquentSignalUpdateRepository::class);
        $this->app->bind(PerformanceSnapshotRepository::class, EloquentPerformanceSnapshotRepository::class);

    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            $appUrl = (string) config('app.url', '');
            if (str_starts_with($appUrl, 'https://')) {
                URL::forceRootUrl($appUrl);
                URL::forceScheme('https');
            }
        }

        Signal::observe(SignalObserver::class);

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        RateLimiter::for('payments-webhook', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('billing-checkout', function (Request $request) {
            $userId = (string) optional($request->user())->getAuthIdentifier();
            $key = $userId !== '' ? 'user:'.$userId : 'ip:'.$request->ip();

            return Limit::perMinute(10)->by('billing-checkout:'.$key);
        });

        RateLimiter::for('signals-api', function (Request $request) {
            $perMinute = max(10, (int) config('api.limits.requests_per_minute', 120));
            $tokenId = (string) $request->attributes->get('api_token_id', '');
            $rawToken = (string) ($request->bearerToken() ?: $request->header('X-API-Key', ''));
            $tokenFingerprint = $rawToken !== ''
                ? substr(hash('sha256', trim($rawToken)), 0, 24)
                : '';

            $key = $tokenId !== ''
                ? 'token-id:'.$tokenId
                : ($tokenFingerprint !== '' ? 'token-fp:'.$tokenFingerprint : 'ip:'.$request->ip());

            return Limit::perMinute($perMinute)->by('signals-api:'.$key);
        });

        RateLimiter::for('signals-api-stream', function (Request $request) {
            $perMinute = max(1, (int) config('api.limits.stream_requests_per_minute', 20));
            $tokenId = (string) $request->attributes->get('api_token_id', '');
            $rawToken = (string) ($request->bearerToken() ?: $request->header('X-API-Key', ''));
            $tokenFingerprint = $rawToken !== ''
                ? substr(hash('sha256', trim($rawToken)), 0, 24)
                : '';

            $key = $tokenId !== ''
                ? 'token-id:'.$tokenId
                : ($tokenFingerprint !== '' ? 'token-fp:'.$tokenFingerprint : 'ip:'.$request->ip());

            return Limit::perMinute($perMinute)->by('signals-api-stream:'.$key);
        });
    }
}
