<?php

use App\Http\Controllers\Console\AccountController;
use App\Http\Controllers\Console\AuditLogController;
use App\Http\Controllers\Console\ExportLimitController;
use App\Http\Controllers\Console\FailedJobController;
use App\Http\Controllers\Console\FinishSupportAccessController;
use App\Http\Controllers\Console\HealthController;
use App\Http\Controllers\Console\MessageController;
use App\Http\Controllers\Console\OrganisationActionController;
use App\Http\Controllers\Console\OrganisationController;
use App\Http\Controllers\Console\PlanController;
use App\Http\Controllers\Console\RecoveryController;
use App\Http\Controllers\Console\RepairTenantDatabaseController;
use App\Http\Controllers\Console\RunBackupController;
use App\Http\Controllers\Console\SecurityController;
use App\Http\Controllers\Console\ShowcaseController;
use App\Http\Controllers\Console\SupportAvailabilityController;
use App\Http\Controllers\Console\SupportDurationsController;
use App\Http\Controllers\Console\TakeSupportRequestController;
use App\Http\Controllers\Console\TeamController;
use App\Http\Controllers\Console\TrialSettingsController;
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
        // « J'ai termine » : la personne ferme elle-meme l'acces qu'une organisation lui a ouvert.
        Route::post('support-access/{grant}/finish', FinishSupportAccessController::class)->middleware('can:console.area,"support"')->name('support-access.finish');
        // « Prendre en charge » une demande d'aide : la personne se rend visible de l'organisation.
        Route::post('support-requests/{supportRequest}/take', TakeSupportRequestController::class)->middleware('can:console.area,"support"')->name('support-requests.take');
        Route::get('organisations/{organisation}', [OrganisationController::class, 'show'])->middleware('can:console.area,"organisations"')->name('organisations.show');

        // Les actions de l'editeur sur une organisation (README section 3) : Fondateurs et
        // Comptabilite.
        Route::post('organisations/{tenant}/suspend', [OrganisationActionController::class, 'suspend'])->name('organisations.suspend');
        Route::post('organisations/{tenant}/reactivate', [OrganisationActionController::class, 'reactivate'])->middleware('can:console.area,"organisation_actions"')->name('organisations.reactivate');
        Route::patch('organisations/{tenant}/plan', [OrganisationActionController::class, 'changePlan'])->name('organisations.plan.update');
        Route::patch('organisations/{tenant}/trial', [OrganisationActionController::class, 'extendTrial'])->name('organisations.trial.update');
        Route::put('organisations/{tenant}/limits', [OrganisationActionController::class, 'updateLimits'])->name('organisations.limits.update');
        Route::post('organisations/{tenant}/deletion', [OrganisationActionController::class, 'scheduleDeletion'])->name('organisations.deletion.schedule');
        Route::delete('organisations/{tenant}/deletion', [OrganisationActionController::class, 'cancelDeletion'])->middleware('can:console.area,"organisation_actions"')->withTrashed()->name('organisations.deletion.cancel');
        Route::get('recovery', [RecoveryController::class, 'index'])->middleware('can:console.area,"recovery"')->name('recovery');
        Route::post('recovery/{tenant}/remind', [RecoveryController::class, 'remind'])->middleware('can:console.area,"recovery"')->name('recovery.remind');
        Route::get('plans', [PlanController::class, 'index'])->middleware('can:console.area,"plans"')->name('plans');
        Route::patch('plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::put('trial', TrialSettingsController::class)->name('trial.update');
        Route::get('health', HealthController::class)->middleware('can:console.area,"health"')->name('health');
        Route::post('health/databases/{tenant}/migrate', RepairTenantDatabaseController::class)->middleware('can:console.area,"health"')->name('health.databases.migrate');
        Route::post('health/backup', RunBackupController::class)->middleware('can:console.area,"health"')->name('health.backup');
        Route::post('health/failed-jobs/{job}/retry', [FailedJobController::class, 'retry'])->middleware('can:console.area,"health"')->name('health.failed-jobs.retry');
        Route::delete('health/failed-jobs/{job}', [FailedJobController::class, 'destroy'])->middleware('can:console.area,"health"')->name('health.failed-jobs.destroy');
        Route::get('showcase', [ShowcaseController::class, 'index'])->middleware('can:console.area,"showcase"')->name('showcase');
        Route::post('showcase/{announcement}/withdraw', [ShowcaseController::class, 'withdraw'])->name('showcase.withdraw');
        Route::get('security', SecurityController::class)->middleware('can:console.area,"security"')->name('security');
        Route::put('security/export-limit', ExportLimitController::class)->name('security.export-limit.update');
        Route::put('security/support-durations', SupportDurationsController::class)->name('security.support-durations.update');
        Route::get('messages', MessageController::class)->middleware('can:console.area,"messages"')->name('messages');
        // Les comptes : recherche, blocage, reinitialisation de la double authentification.
        Route::get('accounts', [AccountController::class, 'index'])->middleware('can:console.area,"accounts"')->name('accounts.index');
        Route::post('accounts/{user}/block', [AccountController::class, 'block'])->middleware('can:console.area,"accounts"')->name('accounts.block');
        Route::delete('accounts/{user}/block', [AccountController::class, 'unblock'])->middleware('can:console.area,"accounts"')->name('accounts.unblock');
        Route::post('accounts/{user}/two-factor-reset', [AccountController::class, 'resetTwoFactor'])->middleware('can:console.area,"accounts"')->name('accounts.two-factor-reset');
        Route::get('audit', AuditLogController::class)->middleware('can:console.area,"audit"')->name('audit');
        // L'equipe editeur (README ecran 34) : reservee aux Fondateurs.
        Route::get('team', [TeamController::class, 'index'])->middleware('can:console.area,"team"')->name('team');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('team/{operator}', [TeamController::class, 'destroy'])->middleware('can:console.area,"team"')->name('team.destroy');
    });
