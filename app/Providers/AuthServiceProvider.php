<?php

namespace App\Providers;

use App\Models\PerformanceSnapshot;
use App\Models\Signal;
use App\Models\SignalUpdate;
use App\Policies\PerformanceSnapshotPolicy;
use App\Policies\SignalPolicy;
use App\Policies\SignalUpdatePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Signal::class => SignalPolicy::class,
        SignalUpdate::class => SignalUpdatePolicy::class,
        PerformanceSnapshot::class => PerformanceSnapshotPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
