<?php

use App\Http\Controllers\Api\AnalysisController;
use Illuminate\Support\Facades\Route;

// Called by the Preflight GitHub Action, authenticated with the job's GitHub OIDC token.
Route::post('analyses', [AnalysisController::class, 'store'])->middleware('throttle:60,1')->name('api.analyses.store');
