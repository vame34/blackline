<?php

namespace Tests\Feature;

use App\DTOs\Signals\SignalListFilters;
use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Models\User;
use App\Repositories\Signals\EloquentSignalRepository;
use App\Services\Preferences\DisplayTimezone;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezonePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preference_is_saved_for_account_and_display_does_not_mutate_utc_dates(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson(route('preferences.timezone'), [
            'timezone' => 'Asia/Novokuznetsk', 'browser_timezone' => 'America/New_York',
        ])->assertNoContent();
        $this->assertSame('Asia/Novokuznetsk', $user->fresh()->timezone);
        $this->get('/api/docs')->assertOk()->assertSee('data-display-timezone="Asia/Novokuznetsk"', false);
        $date = CarbonImmutable::parse('2026-01-01T20:15:00Z');
        $this->assertSame('02.01.2026 03:15', app(DisplayTimezone::class)->format($date));
        $this->assertSame('20:15', $date->format('H:i'));
        $this->get('/profile')->assertNotFound();
    }

    public function test_guest_auto_preference_uses_browser_timezone_and_rejects_invalid_values(): void
    {
        $this->postJson(route('preferences.timezone'), ['timezone' => 'auto', 'browser_timezone' => 'Asia/Kathmandu'])
            ->assertNoContent()->assertSessionHas('display_timezone.browser', 'Asia/Kathmandu');
        $this->assertSame('Asia/Kathmandu', app(DisplayTimezone::class)->resolved());
        $this->postJson(route('preferences.timezone'), ['timezone' => 'UTC+99'])
            ->assertUnprocessable()->assertJsonValidationErrors('timezone');
        $this->postJson(route('preferences.timezone'), ['timezone' => 'auto', 'browser_timezone' => 'fake'])
            ->assertUnprocessable()->assertJsonValidationErrors('browser_timezone');
    }

    public function test_date_filter_uses_local_calendar_day_including_23_hour_dst_day(): void
    {
        foreach (['2026-03-08 04:59:59', '2026-03-08 05:00:00', '2026-03-09 03:59:59', '2026-03-09 04:00:00'] as $at) {
            Signal::query()->create([
                'symbol' => 'BTCUSDT', 'timeframe' => '15m', 'direction' => SignalDirection::Long, 'status' => SignalStatus::Published,
                'entry_price' => 100, 'stop_loss' => 95, 'take_profit' => 110, 'published_at' => $at,
            ]);
        }
        $repository = app(EloquentSignalRepository::class);
        $result = $repository->paginatePublished(new SignalListFilters(
            dateFrom: '2026-03-08', dateTo: '2026-03-08', timezone: 'America/New_York',
        ));
        $this->assertSame(2, $result->total());
        $this->assertEqualsCanonicalizing(['2026-03-08 05:00:00', '2026-03-09 03:59:59'],
            $result->getCollection()->map(fn ($signal) => $signal->published_at->format('Y-m-d H:i:s'))->all());
        $utc = $repository->paginatePublished(new SignalListFilters(dateFrom: '2026-03-08', dateTo: '2026-03-08'));
        $this->assertSame(2, $utc->total());
        $this->assertSame('2026-03-08 05:00:00', $utc->items()[0]->published_at->format('Y-m-d H:i:s'));
    }
}
