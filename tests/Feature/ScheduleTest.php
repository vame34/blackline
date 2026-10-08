<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    public function test_schedule_list_contains_expected_commands(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringNotContainsString('alerts:dispatch', $output);
        $this->assertStringContainsString('signals:run-strategy', $output);
        $this->assertStringContainsString('horizon:snapshot', $output);
        $this->assertStringContainsString('performance:refresh', $output);
    }
}
