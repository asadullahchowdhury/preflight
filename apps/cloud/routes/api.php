<?php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

// Called by the Preflight GitHub Action, authenticated with the job's GitHub OIDC token.
Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:60,1')->name('api.analyses.store');

// Called by `php artisan preflight:stats --push` from production, with the repository's stats token.
Route::post('stats', [StatsController::class, 'store'])->middleware('throttle:30,1')->name('api.stats.store');
