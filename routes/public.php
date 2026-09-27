<?php

use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\PaymentProofController as PublicPaymentProofController;
use App\Http\Controllers\Public\RegistrationController as PublicRegistrationController;
use App\Http\Controllers\Public\RegistrationDeletedController as PublicRegistrationDeletedController;
use App\Http\Controllers\Public\WaitlistController as PublicWaitlistController;
use App\Http\Middleware\EndTenancy;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
 * Le parcours invite non authentifie. `InitializeTenancyBySubdomain` (stancl/tenancy) resout
 * le locataire par sous-domaine et initialise la tenancy avant que la route ne s'execute :
 * c'est le TenantFinder decrit dans CLAUDE.md pour ces liens. `PreventAccessFromCentralDomains`
 * ferme la route sur le domaine principal, ou aucun sous-domaine n'a de sens.
 *
 * Pas de liaison de route sur un identifiant : l'evenement est resolu par son jeton, a
 * l'interieur de la base du locataire deja active. Un jeton d'un autre locataire ne peut donc
 * pas etre trouve : c'est le cloisonnement lui-meme qui rend l'acces croise impossible, pas une
 * verification ajoutee dans le controleur.
 */
Route::middleware([
    PreventAccessFromCentralDomains::class,
    InitializeTenancyBySubdomain::class,
    EndTenancy::class,
    'throttle:public-link',
])->group(function () {
    Route::get('/e/{token}', [PublicEventController::class, 'show'])->name('public.events.show');

    // Le formulaire d'inscription (README ecran 4, etape 4) et sa reservation (ecran 5,
    // etape 5) : la seconde adressee par un jeton de reprise, jamais par l'identifiant de
    // l'inscription (CLAUDE.md, « Securite »).
    // « Inscription supprimee » (README ecran 11) : generique, voir le controleur.
    Route::get('/e/{token}/deleted', PublicRegistrationDeletedController::class)
        ->name('public.registrations.deleted');

    Route::get('/e/{token}/register', [PublicRegistrationController::class, 'create'])
        ->name('public.registrations.create');
    Route::post('/e/{token}/register', [PublicRegistrationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('public.registrations.store');
    Route::get('/e/{token}/register/{resume}', [PublicRegistrationController::class, 'show'])
        ->middleware('throttle:resume')
        ->name('public.registrations.show');
    Route::post('/e/{token}/register/{resume}/retry', [PublicRegistrationController::class, 'retry'])
        ->middleware('throttle:resume')
        ->name('public.registrations.retry');

    // Verification du telephone par code avant la reservation (SECURITY.md C3), quand
    // l'evenement l'exige. Renvoi du code limite a part : chaque envoi coute un message.
    Route::get('/e/{token}/register/{resume}/verify', [PublicRegistrationController::class, 'showVerification'])
        ->middleware('throttle:resume')
        ->name('public.registrations.verify.show');
    Route::post('/e/{token}/register/{resume}/verify', [PublicRegistrationController::class, 'verify'])
        ->middleware('throttle:resume')
        ->name('public.registrations.verify');
    Route::post('/e/{token}/register/{resume}/verify/resend', [PublicRegistrationController::class, 'resendCode'])
        ->middleware('throttle:phone-code')
        ->name('public.registrations.verify.resend');

    // Le lien signe des envois programmes et rappels (README 2.7, etape 8) : une tache planifiee
    // ne connait plus le jeton de reprise en clair, seule son empreinte est stockee. La signature
    // se verifie dans le controleur (`Registration::notificationToken()`), pas de middleware
    // `signed` de Laravel : la construction de l'URL contourne deliberement `route()`/`url()`,
    // qui deriveraient l'hote de la requete HTTP courante plutot que du sous-domaine du
    // locataire (meme raison que `Event::publicUrl()`).
    Route::get('/e/{token}/register/{registration}/link', [PublicRegistrationController::class, 'link'])
        ->name('public.registrations.link');

    // Le depot de la preuve (README ecran 5 etape 2 et 3, ecran 6), etape 6. Cle de debit
    // propre a cette route (CLAUDE.md, table des limiteurs) : 5 par heure, plus stricte que le
    // `public-link` du groupe, deja applique par ailleurs.
    Route::post('/e/{token}/register/{resume}/proof', [PublicPaymentProofController::class, 'store'])
        ->middleware('throttle:proof-submission')
        ->name('public.registrations.proof.store');

    // La liste d'attente (README ecran 10, 2.3) : dernier morceau de l'etape 5. Meme regle de
    // jeton de reprise que la reservation, jamais l'identifiant de l'entree.
    Route::get('/e/{token}/waitlist', [PublicWaitlistController::class, 'create'])
        ->name('public.waitlist.create');
    Route::post('/e/{token}/waitlist', [PublicWaitlistController::class, 'store'])
        ->name('public.waitlist.store');
    Route::get('/e/{token}/waitlist/{resume}', [PublicWaitlistController::class, 'show'])
        ->middleware('throttle:resume')
        ->name('public.waitlist.show');
    Route::post('/e/{token}/waitlist/{resume}/finalize', [PublicWaitlistController::class, 'finalize'])
        ->middleware('throttle:resume')
        ->name('public.waitlist.finalize');
});
