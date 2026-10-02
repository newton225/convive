<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProductTourController;
use App\Http\Controllers\ShowcaseController;
use App\Http\Controllers\Tenants\TenantInvitationController;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\EnsureTwoFactorForProfile;
use Illuminate\Support\Facades\Route;

// Reservee aux visiteurs : un membre connecte est renvoye vers son espace (voir `bootstrap/app.php`).
Route::get('/', HomeController::class)->middleware('guest')->name('home');

// Vitrine des evenements a la une (CLAUDE.md, « Annonce sur le site produit ») : accessible a
// tous, connecte ou non, contrairement a la page d'accueil marketing.
Route::get('evenements-a-la-une', ShowcaseController::class)->name('showcase.index');

// Pages juridiques, ouvertes a tous (connecte ou non) : chaque route fixe le document qu'elle rend.
Route::get('confidentialite', LegalController::class)->defaults('document', 'privacy')->name('legal.privacy');
Route::get('conditions', LegalController::class)->defaults('document', 'terms')->name('legal.terms');
Route::get('mentions-legales', LegalController::class)->defaults('document', 'notice')->name('legal.notice');

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
    Route::post('tours/{tour}/complete', [ProductTourController::class, 'complete'])->name('product-tours.complete');
});

require __DIR__.'/public.php';
require __DIR__.'/events.php';
require __DIR__.'/settings.php';
require __DIR__.'/platform.php';
