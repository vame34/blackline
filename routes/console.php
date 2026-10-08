<?php

use App\Services\Ops\SystemHealthService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('performance:refresh')->daily();
Schedule::command('performance:refresh-terminal-backtests --days=7,14,30,60,90,180,365,730 --from-leverage=1 --to-leverage=30')
    ->weeklyOn(0, '23:55')
    ->withoutOverlapping();
Schedule::command('signals:close-active')
    ->runInBackground()
    ->everyMinute()
    ->withoutOverlapping(5);
Schedule::command('signals:run-strategy')
    ->runInBackground()
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::call(function () {
    app(SystemHealthService::class)->touchSchedulerHeartbeat();
})
    ->everyMinute()
    ->name('system:heartbeat');
