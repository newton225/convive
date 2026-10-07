<?php

use App\Http\Middleware\EndTenancy;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Http\Middleware\EnsureAccountIsNotBlocked;
use App\Http\Middleware\EnsureTenantMembership;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetSecurityHeaders;
use App\Http\Middleware\SetTenantUrlDefaults;
use App\Http\Responses\RateLimitedResponse;
use App\Support\Console\SecurityJournal;
use App\Support\PendingTenantInvitation;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\AuthenticateSession;
use Stancl\Tenancy\Contracts\TenantCouldNotBeIdentifiedException;
use Stancl\Tenancy\Exceptions\NotASubdomainException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Un membre connecte n'a rien a faire sur la vitrine, ni sur la connexion ou l'inscription :
        // le middleware `guest` le renvoie vers son espace (meme destination que la reponse de
        // connexion, voir `RedirectsToCurrentTenant`). Pour revoir la vitrine, il se deconnecte.
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            $tenant = $user->currentTenant ?? $user?->personalTenant() ?? $user?->tenants()->first();

            // Une invitation suivie et pas encore tranchee, ou aucune organisation : l'accueil des
            // invitations, la seule page qui ait du sens pour ce compte (TODO du 2026-10-07).
            if ($tenant === null || PendingTenantInvitation::current($request) !== null) {
                return route('invitations.index', absolute: false);
            }

            return "/{$tenant->slug}/dashboard";
        });

        // Stripe ne porte pas de jeton CSRF : sa signature est verifiee par le paquet.
        $middleware->validateCsrfTokens(except: ['webhooks/stripe', 'webhooks/whatsapp']);

        // AuthenticateSession invalide la session d'un appareil des que le mot de passe a change
        // ailleurs (`Auth::logoutOtherDevices()`, voir `SecurityController::update()`).
        // SetSecurityHeaders genere le nonce CSP de la requete : elle doit s'executer avant
        // HandleInertiaRequests, qui le partage au front, et avant tout rendu de vue.
        $middleware->web(append: [
            AuthenticateSession::class,
            EnforceAbsoluteSessionLifetime::class,
            EnsureAccountIsNotBlocked::class,
            SetSecurityHeaders::class,
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTenantUrlDefaults::class,
        ]);

        // EnsureTenantMembership bascule la connexion de base de donnees (tenancy()->initialize()).
        // Comme les middlewares d'identification de stancl/tenancy, elle doit s'executer avant
        // SubstituteBindings : un sous-modele du back-office ({event}, {unit}, {profile}...)
        // resolu par liaison de route doit deja lire la bonne base au moment ou Laravel le
        // recherche, sinon la liaison ne trouve rien.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureTenantMembership::class,
        );

        // Sans priorite explicite, `EndTenancy` (non liste) reste a la position ou elle a ete
        // declaree dans `routes/public.php`, mais `ThrottleRequests` (liste, en amont dans la
        // priorite par defaut de Laravel) se fait re-ordonner avant elle des que le groupe
        // `web` ajoute d'autres middlewares listes (StartSession...) devant : `EndTenancy` finit
        // alors derriere `throttle` dans le pipeline reellement execute, et son `finally` ne
        // voit jamais la requete bloquee par la limite de debit. La lister explicitement avant
        // `ThrottleRequests` fixe sa position quel que soit ce qui l'entoure.
        $middleware->prependToPriorityList(
            before: ThrottleRequests::class,
            prepend: EndTenancy::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Un sous-domaine ou un chemin qui ne resout aucun locataire renvoie 404, jamais 500 :
        // meme regle que pour un acces croise (CLAUDE.md, « Multi-locataire »), rien ne doit
        // laisser deviner ce qui existe ou non.
        $exceptions->render(fn (TenantCouldNotBeIdentifiedException $e) => abort(404));
        $exceptions->render(fn (NotASubdomainException $e) => abort(404));

        // Ces deux cas sont un 404 attendu, pas une panne : chaque robot qui essaie un sous-domaine
        // au hasard ecrirait sinon une erreur avec sa pile d'appel, et le journal ne servirait plus
        // a voir les vraies.
        $exceptions->dontReport([
            TenantCouldNotBeIdentifiedException::class,
            NotASubdomainException::class,
        ]);

        // Limite de debit atteinte : un message qui dit quand reessayer, jamais une page 429 nue
        // (CLAUDE.md, « Limitation de debit »).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            // « Chaque blocage est journalise » : l'ecran Securite de la console le montre.
            SecurityJournal::rateLimited($request);

            return RateLimitedResponse::for($request, $e);
        });
    })->create();
