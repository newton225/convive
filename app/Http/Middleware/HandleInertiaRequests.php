<?php

namespace App\Http\Middleware;

use App\Enums\NotificationType;
use App\Models\DatabaseNotification;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Console\ConsoleAccess;
use App\Support\Locale;
use App\Support\PlanLimits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Vite;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Translation groups handed to the client. Framework groups such as validation
     * stay on the server: only what the interface renders is shipped.
     *
     * @var array<int, string>
     */
    protected const TranslationGroups = ['common', 'navigation', 'account', 'tenants', 'profiles', 'permissions', 'organisation', 'units', 'payment_accounts', 'events', 'guest', 'proofs', 'seating', 'scan', 'registrations', 'reconciliation', 'reports', 'notifications', 'billing', 'site', 'showcase', 'dashboard', 'audit', 'ticket_template', 'event_settings', 'offline', 'tours', 'console', 'support_access'];

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // L'organisation affichee : celle du membre, ou celle qu'un compte de l'equipe Convive
        // consulte sous un acces de support (pose par `EnsureTenantMembership`). Evaluee au rendu,
        // donc apres ce middleware de route.
        $tenant = fn (): ?Tenant => $this->supportAccessOf($request)->tenant ?? $user?->currentTenant;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // Pour le pied du menu du back-office. Jamais a un visiteur anonyme : il n'a pas a savoir
            // quelle version tourne.
            'appVersion' => $user ? config('convive.version') : null,
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Genere par SetSecurityHeaders, qui s'execute avant ce middleware : lecture seule
            // ici (Vite::cspNonce()), jamais Vite::useCspNonce() qui en tirerait un second.
            'cspNonce' => Vite::cspNonce(),
            'locale' => app()->getLocale(),
            'supportedLocales' => Locale::supported(),
            // Envoyees une seule fois, puis gardees par le navigateur d'une page a l'autre (prop
            // « once » d'Inertia) : elles pesaient l'essentiel de chaque navigation. La cle porte
            // la langue, pour qu'un changement de langue les redemande.
            'translations' => Inertia::once(fn () => $this->translations())->as('translations:'.app()->getLocale()),
            'currentTenant' => fn () => $user && $tenant() ? $user->toUserTenant($tenant()) : null,
            'tenants' => fn () => $user?->toUserTenants(includeCurrent: true) ?? [],
            'notifications' => fn () => $user ? $this->notifications($user) : null,
            // Ce que le membre peut faire dans l'organisation courante : sert uniquement a masquer les
            // liens interdits du menu (chaque route revalide cote serveur).
            'tenantPermissions' => fn () => $user && $tenant() ? $user->toTenantPermissions($tenant()) : null,
            // Plan courant et son usage principal, pour le menu lateral (prototype Convive.dc.html).
            // Les visites guidees deja terminees : une visite ne redemarre pas d'elle-meme.
            'completedTours' => fn () => $user->completed_tours ?? [],
            'currentPlan' => fn () => $user && $tenant() ? $this->currentPlan($tenant()) : null,
            // Le bandeau permanent d'un acces de support en cours (README section 3) : tous les
            // membres le voient, et la personne de l'equipe Convive sait qu'elle consulte.
            'supportAccess' => fn () => $user ? $this->supportAccess($request, $tenant()) : null,
            // Sert uniquement a afficher le lien vers la console ; ses routes revalident.
            'canAccessConsole' => fn () => $user !== null && Gate::forUser($user)->allows('console.access'),
            // Les ecrans de la console que le profil editeur du compte ouvre : sert a n'afficher que
            // ses liens dans le menu de la console ; chaque route revalide.
            'consoleAreas' => fn () => $user !== null ? ConsoleAccess::areasOf($user) : [],
        ];
    }

    private function supportAccessOf(Request $request): ?SupportAccessGrant
    {
        $grant = $request->attributes->get(EnsureTenantMembership::SupportAccessAttribute);

        return $grant instanceof SupportAccessGrant ? $grant : null;
    }

    /**
     * @return array{id: int, operator: string, expiresAt: string, viewing: bool}|null
     */
    private function supportAccess(Request $request, ?Tenant $tenant): ?array
    {
        $viewing = $this->supportAccessOf($request);

        $grant = $viewing ?? ($tenant
            ? SupportAccessGrant::where('tenant_id', $tenant->id)->active()->with('operator')->latest('id')->first()
            : null);

        return $grant === null ? null : [
            'id' => $grant->id,
            'operator' => $grant->operator->name,
            'expiresAt' => $grant->expires_at->toISOString(),
            'viewing' => $viewing !== null,
        ];
    }

    /**
     * @return array{name: string, activeEvents: int, maxActiveEvents: int|null, trialDaysLeft: int|null}
     */
    private function currentPlan(Tenant $tenant): array
    {
        $plan = $tenant->plan();

        return [
            'name' => $plan->name,
            'activeEvents' => PlanLimits::for($tenant)->activeEvents(),
            'maxActiveEvents' => $tenant->limit('max_active_events'),
            // Jours d'essai restants, nul hors essai ou pour un essai sans date de fin.
            'trialDaysLeft' => $tenant->trialDaysLeft(),
        ];
    }

    /**
     * Get the bell of the signed-in member : the unread count, and the latest alerts.
     *
     * Le texte est compose ici, dans la langue courante, a partir du type et des parametres
     * stockes (voir `NotificationType::message()`). Les dix dernieres alertes suffisent a la
     * cloche ; le compteur, lui, porte sur toutes les non lues.
     *
     * @return array{unreadCount: int, latest: array<int, array<string, mixed>>}
     */
    protected function notifications(User $user): array
    {
        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->limit(10)->get()->map(function (DatabaseNotification $alert) {
                $type = NotificationType::tryFrom((string) ($alert->data['type'] ?? ''));

                return [
                    'id' => $alert->id,
                    'type' => $type?->value,
                    'title' => $type?->message($alert->data['params'] ?? []) ?? '',
                    'read' => $alert->read_at !== null,
                    'tenantName' => $alert->data['tenant_name'] ?? null,
                    'createdAt' => $alert->created_at?->toISOString(),
                ];
            })->all(),
        ];
    }

    /**
     * Get the interface translations for the current locale.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function translations(): array
    {
        $locale = app()->getLocale();

        $groups = array_map(
            fn (string $group) => Lang::get($group, [], $locale),
            self::TranslationGroups,
        );

        return array_filter(
            array_combine(self::TranslationGroups, $groups),
            is_array(...),
        );
    }
}
