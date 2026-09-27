<?php

use App\Http\Controllers\Events\EventController;
use App\Http\Controllers\Events\EventSettingsController;
use App\Http\Controllers\Events\PaymentProofController;
use App\Http\Controllers\Events\ReconciliationController;
use App\Http\Controllers\Events\RegistrationController;
use App\Http\Controllers\Events\ReportController;
use App\Http\Controllers\Events\ScanController;
use App\Http\Controllers\Events\SeatingController;
use App\Http\Middleware\EnsureTenantIsNotSuspended;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\EnsureTwoFactorForProfile;
use Illuminate\Support\Facades\Route;

/*
 * Le back-office des evenements. Il vit sous le prefixe du locataire, et non sous `settings` :
 * un evenement est le produit, pas un reglage.
 *
 * `{event}` se resout directement (`Event::find()`) : la base active au moment de la liaison
 * est deja celle du locataire (EnsureTenantMembership, priorise avant SubstituteBindings), un
 * evenement d'une autre organisation n'existe simplement pas sur cette connexion. Pas de
 * `scopeBindings()` ici : elle forcerait Laravel a resoudre `{event}` via une relation
 * `Tenant::events()`, qui n'existe plus (l'evenement vit dans une autre base physique, voir
 * CLAUDE.md, « Multi-locataire »). `scopeBindings()` reste utile pour `{member}` et
 * `{invitation}` sur d'autres groupes de routes, resolus via les relations centrales de `Tenant`.
 */
Route::prefix('{tenant}')
    ->middleware(['auth', 'verified', EnsureTenantMembership::class, EnsureTwoFactorForProfile::class, EnsureTenantIsNotSuspended::class])
    ->group(function () {
        Route::get('events', [EventController::class, 'index'])->name('tenants.events.index');
        Route::get('events/new', [EventController::class, 'create'])->name('tenants.events.create');
        Route::post('events', [EventController::class, 'store'])->name('tenants.events.store');
        Route::get('events/{event}', [EventController::class, 'edit'])->name('tenants.events.edit');
        Route::patch('events/{event}', [EventController::class, 'update'])->name('tenants.events.update');
        Route::post('events/{event}/publish', [EventController::class, 'publish'])->name('tenants.events.publish');
        Route::post('events/{event}/close', [EventController::class, 'close'])->name('tenants.events.close');

        // Vitrine du site produit (CLAUDE.md, « Annonce sur le site produit »), opt-in, distinct
        // de la publication.
        Route::post('events/{event}/announce', [EventController::class, 'announce'])->name('tenants.events.announce');
        Route::delete('events/{event}/announce', [EventController::class, 'withdrawAnnouncement'])->name('tenants.events.announce.withdraw');
        Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('tenants.events.duplicate');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('tenants.events.destroy');

        // Le visuel de l'evenement (README ecran 13), distinct des fichiers de marque de
        // l'organisation.
        Route::post('events/{event}/visual', [EventController::class, 'storeVisual'])->name('tenants.events.visual.store');
        Route::delete('events/{event}/visual', [EventController::class, 'destroyVisual'])->name('tenants.events.visual.destroy');

        // La file de verification des preuves (README ecran 18), etape 6.
        Route::get('events/{event}/proofs', [PaymentProofController::class, 'index'])->name('tenants.events.proofs.index');
        Route::post('events/{event}/proofs/{proof}/approve', [PaymentProofController::class, 'approve'])->name('tenants.events.proofs.approve');
        Route::post('events/{event}/proofs/{proof}/reject', [PaymentProofController::class, 'reject'])->name('tenants.events.proofs.reject');

        // Le plan de salle (README ecran 21), etape 6 : placement manuel, en plus de
        // l'attribution automatique jouee a la validation d'une preuve.
        Route::get('events/{event}/seating', [SeatingController::class, 'index'])->name('tenants.events.seating.index');
        Route::post('events/{event}/seating/{registration}/assign', [SeatingController::class, 'assign'])->name('tenants.events.seating.assign');
        Route::post('events/{event}/seating/constraints', [SeatingController::class, 'storeConstraint'])->name('tenants.events.seating.constraints.store');
        Route::delete('events/{event}/seating/constraints/{constraint}', [SeatingController::class, 'destroyConstraint'])->name('tenants.events.seating.constraints.destroy');

        // Le controle a l'entree (README ecran 26), etape 7.
        Route::get('events/{event}/scan', [ScanController::class, 'index'])->name('tenants.events.scan.index');
        Route::post('events/{event}/scan/verify', [ScanController::class, 'verify'])
            ->middleware('throttle:scan')
            ->name('tenants.events.scan.verify');

        // La base d'inscrits (README ecran 20), etape 9.
        Route::get('events/{event}/registrations', [RegistrationController::class, 'index'])->name('tenants.events.registrations.index');
        Route::post('events/{event}/registrations/{registration}/cancel', [RegistrationController::class, 'cancel'])->name('tenants.events.registrations.cancel');
        Route::post('events/{event}/registrations/purge', [RegistrationController::class, 'purge'])->name('tenants.events.registrations.purge');
        Route::get('events/{event}/registrations/export/excel', [RegistrationController::class, 'exportExcel'])
            ->middleware('throttle:exports')
            ->name('tenants.events.registrations.export.excel');
        Route::get('events/{event}/registrations/export/csv', [RegistrationController::class, 'exportCsv'])
            ->middleware('throttle:exports')
            ->name('tenants.events.registrations.export.csv');
        Route::get('events/{event}/registrations/export/pdf', [RegistrationController::class, 'exportPdf'])
            ->middleware('throttle:exports')
            ->name('tenants.events.registrations.export.pdf');
        Route::get('events/{event}/registrations/export/checklists', [RegistrationController::class, 'exportChecklists'])
            ->middleware('throttle:exports')
            ->name('tenants.events.registrations.export.checklists');

        // Les reglages de l'evenement (README ecran 24) : rappels et regles.
        Route::get('events/{event}/settings', [EventSettingsController::class, 'edit'])->name('tenants.events.settings.edit');
        Route::patch('events/{event}/settings', [EventSettingsController::class, 'update'])->name('tenants.events.settings.update');

        // Les rapports post-evenement (README ecran 22), etape 9.
        Route::get('events/{event}/report', [ReportController::class, 'show'])->name('tenants.events.report.show');
        Route::get('events/{event}/report/export/pdf', [ReportController::class, 'exportPdf'])
            ->middleware('throttle:exports')
            ->name('tenants.events.report.export.pdf');

        // Le rapprochement du releve (README ecran 19), etape 9.
        Route::get('events/{event}/reconciliation', [ReconciliationController::class, 'index'])->name('tenants.events.reconciliation.index');
        Route::post('events/{event}/reconciliation/import', [ReconciliationController::class, 'import'])
            ->middleware('throttle:exports')
            ->name('tenants.events.reconciliation.import');
        Route::post('events/{event}/reconciliation/lines/{line}/resolve', [ReconciliationController::class, 'resolve'])
            ->name('tenants.events.reconciliation.resolve');
    });
