<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->get(route('login'))->assertOk();
        }
    }
}

