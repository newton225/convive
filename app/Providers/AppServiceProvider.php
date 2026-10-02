<?php

namespace App\Providers;

use App\Contracts\SubscriptionBillingGateway;
use App\Contracts\WhatsAppSender;
use App\Enums\ConsoleArea;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\AuditEntry;
use App\Models\ScanEvent;
use App\Models\User;
use App\Notifications\Channels\WhatsAppChannel;
use App\Policies\AuditPolicy;
use App\Policies\ReportPolicy;
use App\Policies\ScanPolicy;
use App\Support\AuditChain;
use App\Support\AuditTrail;
use App\Support\Console\ConsoleAccess;
use App\Support\EventReport;
use App\Support\LogWhatsAppSender;
use App\Support\Search\UnaccentedSearch;
use App\Support\Stripe\StripeApi;
use App\Support\Stripe\StripeSubscriptionBillingGateway;
use App\Support\UnconfiguredBillingGateway;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Exports et imports de releve permis par heure et par utilisateur. SECURITY.md M3 en demande
     * 5 : compromis accepte par le proprietaire du projet (2026-10-01), la limite basse bloquait
     * l'impression des listes de controle de plusieurs evenements le meme jour. La fuite par
     * export reste couverte par la journalisation, l'alerte au Proprietaire et le filigrane.
     */
    public const ExportsPerHour = 30;

    /**
     * Register any application services.
     *
     * `WhatsAppSender` derriere une interface dediee (CLAUDE.md, « Pile imposee ») : seul cet
     * appel change le jour ou un vrai client WhatsApp Business API remplace le palliatif qui
     * ecrit dans le journal.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppSender::class, LogWhatsAppSender::class);

        // Le fournisseur de paiement de l'abonnement : Stripe des que `STRIPE_SECRET` est renseigne,
        // sinon chaque operation refuse explicitement (l'ecran d'abonnement affiche un message clair).
        $this->app->bind(StripeApi::class, fn () => new StripeApi(new StripeClient((string) config('services.stripe.secret'))));
        $this->app->bind(SubscriptionBillingGateway::class, fn ($app) => filled(config('services.stripe.secret'))
            ? $app->make(StripeSubscriptionBillingGateway::class)
            : $app->make(UnconfiguredBillingGateway::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureActivityLog();
        self::configurePaymentProofDisk();
        $this->configureSessionsAndProxies();

        Notification::extend('whatsapp', fn ($app) => $app->make(WhatsAppChannel::class));

        // Recherche sans accents : la base centrale et celle de chaque organisation la recoivent a
        // leur ouverture (`UnaccentedSearch`).
        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => UnaccentedSearch::prepare($event->connection));

        foreach (DB::getConnections() as $connection) {
            UnaccentedSearch::prepare($connection);
        }
    }

    /**
     * Force le disque des preuves de paiement a se servir en piece jointe (SECURITY.md H1) :
     * `Illuminate\Filesystem\ServeFile` sert un disque local en affichage direct par defaut, ce
     * qui convient a un logo de marque mais jamais a une capture d'ecran deposee par un invite
     * inconnu. Point d'extension officiel (`serveUsing()`), pas une reecriture de la route de
     * service generee par `serve => true`.
     *
     * Statique et publique, pas `protected` comme les autres `configure*()` d'ici : instancier
     * ce provider via le conteneur pour rappeler la methode (`app(AppServiceProvider::class)`)
     * echoue, son constructeur attend l'instance `Application` que Laravel lui passe a l'amorcage,
     * pas une resolue a la volee. Les tests qui isolent ce disque sur une racine temporaire
     * doivent pouvoir reposer ce comportement une fois le disque oublie
     * (`Storage::forgetDisk()`), voir `Tests\Feature\Public\PaymentProofTest`.
     */
    public static function configurePaymentProofDisk(): void
    {
        Storage::disk('payment_proofs')->serveUsing(
            fn (Request $request, string $path, array $headers) => Storage::disk('payment_proofs')
                // `no-referrer` (SECURITY.md H2) : l'URL signee d'un recu ne doit jamais partir
                // dans l'en-tete `Referer` d'une page tierce. La route de service generee par
                // `serve => true` ne passe pas par `SetSecurityHeaders` (groupe `web`).
                ->download($path, headers: [
                    'X-Content-Type-Options' => 'nosniff',
                    'Referrer-Policy' => 'no-referrer',
                    ...$headers,
                ]),
        );
    }

    /**
     * Proxys de confiance et depart de la session (SECURITY.md C3 et « Deconnexion et sessions »).
     *
     * `TrustProxies::at()` ici plutot que `trustProxies()` dans `bootstrap/app.php` : la liste
     * vient de la configuration, qui n'est pas encore chargee quand ce fichier s'execute.
     */
    protected function configureSessionsAndProxies(): void
    {
        $proxies = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('convive.security.trusted_proxies')),
        )));

        // `*` n'est jamais applique, meme demande : `convive:production-check` le signale.
        if ($proxies !== [] && ! in_array('*', $proxies, true)) {
            TrustProxies::at($proxies);
        }

        Event::listen(Login::class, fn () => session()->put(
            EnforceAbsoluteSessionLifetime::SessionKey,
            now()->getTimestamp(),
        ));
    }

    /**
     * Attache l'adresse IP et l'agent utilisateur a chaque entree du journal d'audit
     * (CLAUDE.md, « Securite » : « acteur, action, ressource, adresse IP et agent utilisateur »).
     *
     * Point d'extension du modele plutot qu'un ajout a chacun des appels `activity()->...->log()`
     * repartis dans l'application : un seul endroit, aucun oubli possible sur un futur appel.
     * Hors contexte HTTP (console, tache planifiee), `request()->ip()` et `->userAgent()` valent
     * simplement `null` : rien a deviner, rien a falsifier.
     */
    protected function configureActivityLog(): void
    {
        // `AuditEntry`, pas `Activity` : les evenements de modele sont propres a chaque classe, et
        // c'est `AuditEntry` que le paquet instancie (`activitylog.activity_model`).
        AuditEntry::creating(function (AuditEntry $activity): void {
            $activity->properties = collect($activity->properties)->merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // En dernier : l'empreinte couvre l'adresse IP et l'agent utilisateur (SECURITY.md M6).
            AuditChain::link($activity);
        });

        AuditEntry::created(fn () => AuditChain::release());
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        $this->configureRateLimiters();
        $this->configurePolicies();

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register the named rate limiters listed in CLAUDE.md.
     *
     * Deux sont definis pour l'instant : le lien public d'inscription, et la soumission de
     * preuve, les deux surfaces non authentifiees construites jusqu'ici. Les autres (2FA hors
     * Fortify, reprise, scan, exports) arrivent avec les etapes qui les introduisent.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('public-link', fn ($request) => Limit::perMinute(20)->by($request->ip()));

        // Cle inscription plus adresse IP (table de CLAUDE.md) : le jeton de reprise identifie
        // l'inscription sans exposer son identifiant sequentiel dans la cle du limiteur.
        RateLimiter::for('proof-submission', fn ($request) => Limit::perHour(5)
            ->by($request->route('resume').'|'.$request->ip()));

        // Par utilisateur : un agent d'accueil authentifie, jamais par IP (plusieurs postes
        // d'un meme poste de controle peuvent partager une adresse).
        RateLimiter::for('scan', fn ($request) => Limit::perMinute(60)->by($request->user()?->id));

        // Demande d'aide au support : chaque demande ecrit a toute l'equipe Convive.
        RateLimiter::for('support-request', fn ($request) => Limit::perHour(5)->by($request->user()?->id));

        // Exports (Excel/CSV/PDF/listes de controle) et import de releve (table de CLAUDE.md).
        // 30 par heure : un organisateur qui imprime les listes de controle de plusieurs
        // evenements le meme jour atteignait l'ancienne limite de 5 sans rien faire d'anormal.
        RateLimiter::for('exports', fn ($request) => Limit::perHour(self::ExportsPerHour)->by($request->user()?->id));

        // Renvoi du code de verification du telephone (SECURITY.md C3) : chaque envoi coute un
        // message, 3 par tranche de 10 minutes et par inscription plus IP.
        RateLimiter::for('phone-code', fn ($request) => Limit::perMinutes(10, 3)
            ->by((string) $request->route('resume').'|'.$request->ip()));

        // Lien de reprise (table de CLAUDE.md, SECURITY.md H3) : 10 par heure, jeton plus IP.
        RateLimiter::for('resume', fn ($request) => Limit::perHour(10)
            ->by((string) $request->route('resume').'|'.$request->ip()));

        // Creation de reservation par le lien public (SECURITY.md C3) : une reservation bloque des
        // places pendant sa duree, un script qui en cree en boucle epuise l'evenement. Plafond
        // par adresse IP, et plus large par sous-reseau /24 (IPv4) pour les rotations d'adresse
        // voisines. Fenetre de dix minutes, la duree d'une reservation par defaut.
        RateLimiter::for('registration', function ($request) {
            $ip = (string) $request->ip();
            $subnet = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                ? implode('.', array_slice(explode('.', $ip), 0, 3)).'.0/24'
                : $ip;

            return [
                Limit::perMinutes(10, 5)->by('registration:ip:'.$ip),
                Limit::perMinutes(10, 20)->by('registration:subnet:'.$subnet),
            ];
        });

        // Rejeu du second facteur avant une action sensible (comptes de versement,
        // SECURITY.md C1). Cle session utilisateur (table de CLAUDE.md), pas `login.id` :
        // contrairement au limiteur `two-factor` de Fortify, l'utilisateur est deja authentifie.
        RateLimiter::for('two-factor-reconfirm', fn ($request) => Limit::perMinute(5)
            ->by($request->user()?->id ?: $request->session()->getId()));
    }

    /**
     * Register the policies that are not resolved by Laravel's model naming convention
     * (`App\Policies\ScanPolicy` covers an action, not a single Eloquent model).
     */
    protected function configurePolicies(): void
    {
        Gate::policy(ScanEvent::class, ScanPolicy::class);
        Gate::policy(EventReport::class, ReportPolicy::class);
        Gate::policy(AuditTrail::class, AuditPolicy::class);

        // La console d'exploitation (README section 3) : ouverte a l'equipe editeur, chaque profil
        // n'y ouvrant que ses ecrans. `ConsoleAccess` en est la seule source de verite.
        Gate::define('console.access', fn (User $user) => ConsoleAccess::profileOf($user) !== null);
        Gate::define('console.area', fn (User $user, string $area) => ($zone = ConsoleArea::tryFrom($area)) !== null
            && ConsoleAccess::allows($user, $zone));
    }
}
