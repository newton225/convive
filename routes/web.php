<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Tenants\TenantInvitationController;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\EnsureTwoFactorForProfile;
use Illuminate\Support\Facades\Route;

// Reservee aux visiteurs : un membre connecte est renvoye vers son espace (voir `bootstrap/app.php`).
Route::get('/', HomeController::class)->middleware('guest')->name('home');

Route::put('locale', [LocaleController::class, 'update'])->name('locale.update');

// Les notifications de Stripe (paiement reussi, echec, fin d'abonnement), etape 10. Sur le domaine
// central, sans session ni organisation : la signature de la requete fait foi (voir
// `spatie/laravel-stripe-webhooks`), pas un jeton CSRF.
Route::stripeWebhooks('webhooks/stripe');

Route::prefix('{current_tenant}')
    ->middleware(['auth', 'verified', EnsureTenantMembership::class, EnsureTwoFactorForProfile::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TenantInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TenantInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/public.php';
require __DIR__.'/events.php';
require __DIR__.'/settings.php';
