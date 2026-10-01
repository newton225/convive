<?php

use App\Http\Controllers\Console\AuditLogController;
use App\Http\Controllers\Console\HealthController;
use App\Http\Controllers\Console\OrganisationController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\RecoveryController;
use App\Http\Controllers\Console\RepairTenantDatabaseController;
use App\Http\Controllers\Console\ShowcaseController;
use App\Http\Controllers\Console\SupportAvailabilityController;
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
        // Chaque ecran n'est ouvert qu'aux profils editeur qui en ont la zone (`ConsoleProfile`).
        Route::get('organisations', [OrganisationController::class, 'index'])->middleware('can:console.area,"organisations"')->name('organisations.index');
        // Acces du support : chacun choisit d'apparaitre ou non dans la liste proposee aux
        // organisations.
        Route::put('support-availability', SupportAvailabilityController::class)->middleware('can:console.area,"support"')->name('support-availability.update');
        Route::get('organisations/{organisation}', [OrganisationController::class, 'show'])->middleware('can:console.area,"organisations"')->name('organisations.show');
        Route::get('recovery', RecoveryController::class)->middleware('can:console.area,"recovery"')->name('recovery');
        Route::get('plans', PlanController::class)->middleware('can:console.area,"plans"')->name('plans');
        Route::get('health', HealthController::class)->middleware('can:console.area,"health"')->name('health');
        Route::post('health/databases/{tenant}/migrate', RepairTenantDatabaseController::class)->middleware('can:console.area,"health"')->name('health.databases.migrate');
        Route::get('showcase', ShowcaseController::class)->middleware('can:console.area,"showcase"')->name('showcase');
        Route::get('audit', AuditLogController::class)->middleware('can:console.area,"audit"')->name('audit');
        // L'equipe editeur (README ecran 34) : reservee aux Fondateurs.
        Route::get('team', [TeamController::class, 'index'])->middleware('can:console.area,"team"')->name('team');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{operator}', [TeamController::class, 'destroy'])->middleware('can:console.area,"team"')->name('team.destroy');
    });
