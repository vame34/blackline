<?php

namespace App\Http\Controllers\Ops;

use App\Services\Ops\SystemHealthService;
use Illuminate\Http\JsonResponse;

class SystemHealthController
{
    public function __invoke(SystemHealthService $service): JsonResponse
    {
        $status = $service->status();

        return response()->json($status, $status['ok'] ? 200 : 503);
    }
}
