<?php

namespace Tests\Feature;

use Tests\TestCase;

class TelemetryContextTest extends TestCase
{
    public function test_response_includes_request_id_header(): void
    {
        $response = $this->get('/');

        $response->assertHeader('X-Request-Id');
    }
}
