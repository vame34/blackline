<?php

namespace Tests\Unit;

use Tests\TestCase;

class HorizonConfigTest extends TestCase
{
    public function test_horizon_config_is_loaded(): void
    {
        $config = config('horizon');

        $this->assertIsArray($config);
        $this->assertNotEmpty(config('horizon.prefix'));
        $this->assertIsArray(config('horizon.defaults'));
    }
}
