<?php

use App\Http\Controllers\Api\V1\SignalController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware(['api.security_telemetry', 'throttle:signals-api', 'auth.api_token'])
    ->group(function (): void {
        Route::get('me', [SignalController::class, 'me']);
        Route::get('instruments', [SignalController::class, 'instruments']);
        Route::get('signals', [SignalController::class, 'index']);
        Route::get('signals/stream', [SignalController::class, 'stream'])->middleware('throttle:signals-api-stream');
        Route::get('signals/{id}', [SignalController::class, 'show'])->whereNumber('id');
        Route::get('risk-model', [SignalController::class, 'riskModel']);
        Route::get('risk-model/version', [SignalController::class, 'riskModelVersion']);
    });
