<?php

use App\Http\Controllers\Console\AuditLogController;
use App\Http\Controllers\Console\HealthController;
use App\Http\Controllers\Console\OrganisationController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\RecoveryController;
use App\Http\Controllers\Console\ShowcaseController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Middleware\EnsureConsoleOperator;
use Illuminate\Support\Facades\Route;

// Console d'exploitation de l'editeur (README section 3, ecrans 27 a 34). PROVISOIRE : sous
// `/console` du domaine central, avec les comptes existants. Le domaine dedie (`admin.<domaine>`)
// arrive avec les comptes editeur distincts : une session propre a ce domaine suppose ses propres
// comptes et son propre formulaire de connexion.
Route::prefix('console')
    ->name('console.')
    ->middleware(['auth', 'verified', EnsureConsoleOperator::class])
    ->group(function () {
        Route::redirect('/', '/console/organisations')->name('home');
        Route::get('organisations', [OrganisationController::class, 'index'])->name('organisations.index');
        Route::get('organisations/{organisation}', [OrganisationController::class, 'show'])->name('organisations.show');
        Route::get('recovery', RecoveryController::class)->name('recovery');
        Route::get('plans', PlanController::class)->name('plans');
        Route::get('health', HealthController::class)->name('health');
        Route::get('showcase', ShowcaseController::class)->name('showcase');
        Route::get('audit', AuditLogController::class)->name('audit');
        Route::get('team', TeamController::class)->name('team');
    });
