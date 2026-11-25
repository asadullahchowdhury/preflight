<?php

use App\Http\Controllers\Admin\BetaController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\Auth\DevLoginController;
use App\Http\Controllers\Auth\GitHubLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocsController;
use App\Http\Controllers\GitHub\WebhookController;
use App\Http\Controllers\InstallationController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\WaitlistController;
use App\Http\Middleware\EnsureBetaAccess;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('docs/{page?}', [DocsController::class, 'show'])->where('page', '[a-z0-9-]+')->name('docs');

Route::middleware('guest')->group(function () {
    Route::get('login', [GitHubLoginController::class, 'show'])->name('login');
    Route::get('auth/github/redirect', [GitHubLoginController::class, 'redirect'])->name('github.redirect');
    Route::get('auth/github/callback', [GitHubLoginController::class, 'callback'])->name('github.callback');

    if (app()->isLocal()) {
        Route::get('dev/login', DevLoginController::class)->name('dev.login');
    }
});

Route::post('logout', [GitHubLoginController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('waitlist', [WaitlistController::class, 'show'])->name('waitlist');
    Route::patch('waitlist', [WaitlistController::class, 'update'])->name('waitlist.update');

    Route::middleware('can:admin')->prefix('admin')->group(function () {
        Route::get('beta', [BetaController::class, 'index'])->name('admin.beta');
        Route::post('beta/{user}/approve', [BetaController::class, 'approve'])->name('admin.beta.approve');
        Route::post('beta/{user}/revoke', [BetaController::class, 'revoke'])->name('admin.beta.revoke');
    });
});

// Everything below needs beta access: approved accounts only while the private beta is on.
Route::middleware(['auth', EnsureBetaAccess::class])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('repositories/{repository}', [RepositoryController::class, 'show'])->name('repositories.show');
    Route::post('repositories/{repository}/stats-token', [RepositoryController::class, 'issueStatsToken'])->middleware('throttle:10,1')->name('repositories.stats-token.store');
    Route::delete('repositories/{repository}/stats-token', [RepositoryController::class, 'revokeStatsToken'])->name('repositories.stats-token.destroy');
    Route::get('analyses/{analysis}', [AnalysisController::class, 'show'])->name('analyses.show');

    Route::get('github/setup', [InstallationController::class, 'setup'])->name('github.setup');
    Route::post('installations/sync', [InstallationController::class, 'sync'])->middleware('throttle:6,1')->name('installations.sync');
});

Route::post('github/webhook', WebhookController::class)->name('github.webhook');

require __DIR__.'/settings.php';
