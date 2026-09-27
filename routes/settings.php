<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Settings\NotificationPreferenceController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\TwoFactorReconfirmationController;
use App\Http\Controllers\Tenants\AuditLogController;
use App\Http\Controllers\Tenants\BillingController;
use App\Http\Controllers\Tenants\OrganisationController;
use App\Http\Controllers\Tenants\PaymentAccountController;
use App\Http\Controllers\Tenants\ProfileController as TenantProfileController;
use App\Http\Controllers\Tenants\TenantController;
use App\Http\Controllers\Tenants\TenantInvitationController;
use App\Http\Controllers\Tenants\TenantMemberController;
use App\Http\Controllers\Tenants\TicketTemplateController;
use App\Http\Controllers\Tenants\UnitController;
use App\Http\Middleware\EnsureRecentTwoFactorConfirmation;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\EnsureTwoFactorForProfile;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    // Code de scan a 4 chiffres (SECURITY.md M8) : choisi par le membre, depuis l'ecran Securite
    // ou a la premiere ouverture de l'ecran de scan.
    Route::put('settings/scan-pin', [SecurityController::class, 'updateScanPin'])
        ->middleware('throttle:6,1')
        ->name('scan-pin.update');

    // Appareils connectes (SECURITY.md, « Deconnexion et sessions ») : fermer les autres sessions.
    Route::delete('settings/sessions/others', [SecurityController::class, 'destroyOtherSessions'])
        ->middleware('throttle:6,1')
        ->name('other-sessions.destroy');

    // Rejeu du second facteur juste avant une action sensible (comptes de versement,
    // SECURITY.md C1), pose par `EnsureRecentTwoFactorConfirmation`. Hors du prefixe de
    // l'organisation, comme `security.edit` : une reconfirmation ne depend d'aucun locataire.
    Route::get('settings/confirm-two-factor', [TwoFactorReconfirmationController::class, 'show'])
        ->name('two-factor.reconfirm.show');
    Route::post('settings/confirm-two-factor', [TwoFactorReconfirmationController::class, 'store'])
        ->middleware('throttle:two-factor-reconfirm')
        ->name('two-factor.reconfirm.store');

    Route::inertia('settings/appearance', 'settings/appearance')->name('appearance.edit');

    // Les alertes (README section 5), etape 10 : la cloche et les preferences de canal. Par
    // utilisateur, hors de tout prefixe d'organisation.
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('settings/notifications', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    Route::get('settings/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::post('settings/tenants', [TenantController::class, 'store'])->name('tenants.store');

    // Pas de `scopeBindings()` : elle resoudrait {payment_account}, {unit} et {profile} via
    // des relations `Tenant` qui n'existent plus (ces modeles vivent dans une autre base
    // physique, voir CLAUDE.md, « Multi-locataire »). {member} et {invitation} restent surs
    // sans elle : chaque action verifie deja explicitement l'appartenance au locataire.
    Route::middleware(EnsureTenantMembership::class)->group(function () {
        // Sortir d'une organisation ou en changer reste toujours possible : un membre dont
        // le profil exige la 2FA ne doit pas se retrouver enferme dans un espace.
        Route::post('settings/tenants/{tenant}/switch', [TenantController::class, 'switch'])->name('tenants.switch');
        Route::delete('settings/tenants/{tenant}/leave', [TenantController::class, 'leave'])->name('tenants.leave');

        Route::middleware(EnsureTwoFactorForProfile::class)->group(function () {
            Route::get('settings/tenants/{tenant}', [TenantController::class, 'edit'])->name('tenants.edit');
            Route::patch('settings/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
            Route::delete('settings/tenants/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');

            Route::patch('settings/tenants/{tenant}/members/{member}', [TenantMemberController::class, 'update'])->middleware(RequirePassword::class)->name('tenants.members.update');
            Route::delete('settings/tenants/{tenant}/members/{member}', [TenantMemberController::class, 'destroy'])->middleware(RequirePassword::class)->name('tenants.members.destroy');

            Route::post('settings/tenants/{tenant}/invitations', [TenantInvitationController::class, 'store'])->name('tenants.invitations.store');
            Route::delete('settings/tenants/{tenant}/invitations/{invitation}', [TenantInvitationController::class, 'destroy'])->name('tenants.invitations.destroy');

            Route::get('settings/tenants/{tenant}/organisation', [OrganisationController::class, 'edit'])->name('tenants.organisation.edit');
            Route::patch('settings/tenants/{tenant}/organisation/legal', [OrganisationController::class, 'updateLegalIdentity'])->middleware(RequirePassword::class)->name('tenants.organisation.legal');
            Route::patch('settings/tenants/{tenant}/organisation/brand', [OrganisationController::class, 'updateBrand'])->name('tenants.organisation.branding');
            Route::patch('settings/tenants/{tenant}/organisation/subdomain', [OrganisationController::class, 'updateSubdomain'])->middleware(RequirePassword::class)->name('tenants.organisation.subdomain');
            Route::post('settings/tenants/{tenant}/organisation/files/{file}', [OrganisationController::class, 'storeFile'])->name('tenants.organisation.files.store');
            Route::delete('settings/tenants/{tenant}/organisation/files/{file}', [OrganisationController::class, 'destroyFile'])->name('tenants.organisation.files.destroy');

            // Re-authentification forte immediatement avant toute action sur un compte de
            // versement, independamment de la session en cours (SECURITY.md C1).
            Route::middleware(RequirePassword::class)->group(function () {
                Route::get('settings/tenants/{tenant}/payment-accounts', [PaymentAccountController::class, 'index'])->name('tenants.payment-accounts.index');

                // Le second facteur rejoue en plus du mot de passe, mais seulement sur les
                // routes qui modifient effectivement quelque chose : « juste avant la
                // modification » (CLAUDE.md), pas sur la simple lecture de l'ecran.
                Route::middleware(EnsureRecentTwoFactorConfirmation::class)->group(function () {
                    Route::post('settings/tenants/{tenant}/payment-accounts', [PaymentAccountController::class, 'store'])->name('tenants.payment-accounts.store');
                    Route::patch('settings/tenants/{tenant}/payment-accounts/{payment_account}', [PaymentAccountController::class, 'update'])->name('tenants.payment-accounts.update');
                    Route::post('settings/tenants/{tenant}/payment-accounts/{payment_account}/approve', [PaymentAccountController::class, 'approve'])->name('tenants.payment-accounts.approve');
                    Route::post('settings/tenants/{tenant}/payment-accounts/{payment_account}/cancel', [PaymentAccountController::class, 'cancel'])->name('tenants.payment-accounts.cancel');
                    Route::delete('settings/tenants/{tenant}/payment-accounts/{payment_account}', [PaymentAccountController::class, 'destroy'])->name('tenants.payment-accounts.destroy');
                });
            });

            // L'abonnement (README ecran 16), etape 10 : reste hors de `EnsureTenantIsNotSuspended`,
            // une organisation suspendue regularise par cet ecran.
            Route::get('settings/tenants/{tenant}/billing', [BillingController::class, 'show'])->name('tenants.billing.show');
            Route::post('settings/tenants/{tenant}/billing/checkout/{plan}', [BillingController::class, 'checkout'])->middleware(RequirePassword::class)->name('tenants.billing.checkout');
            Route::post('settings/tenants/{tenant}/billing/payment-method', [BillingController::class, 'paymentMethod'])->middleware(RequirePassword::class)->name('tenants.billing.payment-method');
            Route::post('settings/tenants/{tenant}/billing/cancel', [BillingController::class, 'cancel'])->middleware(RequirePassword::class)->name('tenants.billing.cancel');

            // Journalisation (README ecran 23) et gabarit du billet (ecran 15).
            Route::get('settings/tenants/{tenant}/audit', [AuditLogController::class, 'index'])->name('tenants.audit.index');
            Route::get('settings/tenants/{tenant}/ticket-template', [TicketTemplateController::class, 'edit'])->name('tenants.ticket-template.edit');
            Route::patch('settings/tenants/{tenant}/ticket-template', [TicketTemplateController::class, 'update'])->name('tenants.ticket-template.update');

            Route::get('settings/tenants/{tenant}/units', [UnitController::class, 'index'])->name('tenants.units.index');
            Route::post('settings/tenants/{tenant}/units', [UnitController::class, 'store'])->name('tenants.units.store');
            Route::patch('settings/tenants/{tenant}/units/{unit}', [UnitController::class, 'update'])->name('tenants.units.update');
            Route::delete('settings/tenants/{tenant}/units/{unit}', [UnitController::class, 'destroy'])->name('tenants.units.destroy');

            Route::get('settings/tenants/{tenant}/profiles', [TenantProfileController::class, 'index'])->name('tenants.profiles.index');
            Route::get('settings/tenants/{tenant}/profiles/create', [TenantProfileController::class, 'create'])->middleware(RequirePassword::class)->name('tenants.profiles.create');
            Route::get('settings/tenants/{tenant}/profiles/{profile}/edit', [TenantProfileController::class, 'edit'])->middleware(RequirePassword::class)->name('tenants.profiles.edit');
            Route::post('settings/tenants/{tenant}/profiles', [TenantProfileController::class, 'store'])->middleware(RequirePassword::class)->name('tenants.profiles.store');
            Route::patch('settings/tenants/{tenant}/profiles/{profile}', [TenantProfileController::class, 'update'])->middleware(RequirePassword::class)->name('tenants.profiles.update');
            Route::post('settings/tenants/{tenant}/profiles/{profile}/duplicate', [TenantProfileController::class, 'duplicate'])->middleware(RequirePassword::class)->name('tenants.profiles.duplicate');
            Route::delete('settings/tenants/{tenant}/profiles/{profile}', [TenantProfileController::class, 'destroy'])->middleware(RequirePassword::class)->name('tenants.profiles.destroy');
        });
    });
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
