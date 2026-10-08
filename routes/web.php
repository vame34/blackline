<?php

use App\Http\Controllers\Ops\SystemHealthController;
use App\Http\Controllers\Pages\PerformanceHistoryController;
use App\Http\Controllers\Preferences\TimezoneController;
use App\Livewire\Performance;
use App\Livewire\Signals\Index as SignalsIndex;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/signals')->name('home');
Route::redirect('/dashboard', '/signals')->name('dashboard');
Route::get('health', SystemHealthController::class)->name('health');
Route::get('lang/{locale}', function (string $locale, Request $request) {
    abort_unless(in_array($locale, (array) config('locales.supported', ['en']), true), 404);
    $request->session()->put('locale', $locale);
    $request->user()?->forceFill(['locale' => $locale])->save();

    return redirect()->back();
})->name('locale.switch');
Route::view('api/docs', 'pages.api-docs')->name('api.docs');
Route::get('api/openapi.yaml', fn () => response()->file(base_path('docs/openapi.yaml'), [
    'Content-Type' => 'application/yaml; charset=UTF-8',
]))->name('api.openapi');

Route::middleware(['auth', 'not.banned', 'verified'])->group(function () {
    Route::get('signals', SignalsIndex::class)->middleware('no.store')->name('signals.index');
    Route::get('performance/terminal', Performance::class)->name('performance.terminal');
    Route::get('performance/terminal/history', PerformanceHistoryController::class)->name('performance.terminal.history');
});
Route::post('preferences/timezone', TimezoneController::class)->middleware('not.banned')->name('preferences.timezone');
require __DIR__.'/auth.php';
