<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * Une organisation reellement publiable : identite legale complete, sous-domaine, et un
     * compte de versement visible.
     */
    private function publishableTenant(User $owner, string $subdomain = 'convive-ci'): Tenant
    {
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();

        $tenant->update(['subdomain' => $subdomain]);

        $tenant->asCurrent(fn () => PaymentAccount::factory()->create());

        return $tenant->fresh();
    }

    /**
     * Un evenement publie : jeton pose, comptes de versement visibles rattaches.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->published()->create($attributes);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    /**
     * `Event::publicUrl()` lit `Tenant::current()`, indisponible une fois la tenancy terminee
     * (EnsureTenantMembership/EndTenancy la ferment a la fin de chaque requete). On reconstruit
     * la meme adresse a partir du sous-domaine et du jeton, deja connus du test.
     */
    private function publicUrl(Tenant $tenant, Event $event): string
    {
        if ($event->public_token === null) {
            throw new RuntimeException("L'evenement de test n'a pas de jeton public.");
        }

        return $this->urlFor($tenant->subdomain, $event->public_token);
    }

    /**
     * Construit l'adresse publique d'un jeton sans passer par un evenement reel : pour tester
     * un jeton ou un sous-domaine qui n'existe pas. `InitializeTenancyBySubdomain` (stancl/tenancy)
     * resout le locataire par le hote de la requete, pas par un parametre de route : `route()`
     * ne peut donc plus fabriquer cette adresse, contrairement a l'ancien `Route::domain()`.
     */
    private function urlFor(string $subdomain, string $token): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$token}";
    }

    public function test_la_page_publique_s_affiche_pour_un_evenement_publie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['name' => 'Diner de gala 2026']);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/event')
                ->where('event.name', 'Diner de gala 2026')
                ->where('tenant.name', $tenant->name),
            );
    }

    public function test_un_jeton_inconnu_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);

        $url = $this->urlFor($tenant->subdomain, str_repeat('a', 64));

        $this->get($url)->assertNotFound();
    }

    public function test_un_sous_domaine_inconnu_recoit_404(): void
    {
        $url = $this->urlFor('aucune-organisation-ici', str_repeat('a', 64));

        $this->get($url)->assertNotFound();
    }

    public function test_un_sous_domaine_inconnu_n_ecrit_pas_d_erreur_au_journal(): void
    {
        // Un 404 attendu : un robot qui essaie des sous-domaines ne doit pas remplir le journal.
        Exceptions::fake();

        $this->get($this->urlFor('aucune-organisation-ici', str_repeat('a', 64)))->assertNotFound();

        Exceptions::assertNothingReported();
    }

    public function test_un_evenement_jamais_publie_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        // Un brouillon n'a pas de jeton : on teste directement l'absence de reponse pour un
        // jeton qui n'existe nulle part, exactement ce qu'un jeton devine ou perime produirait.
        $this->assertNull($event->public_token);

        $url = $this->urlFor($tenant->subdomain, str_repeat('a', 64));

        $this->get($url)->assertNotFound();
    }

    public function test_le_jeton_d_un_evenement_ne_repond_pas_sous_un_autre_sous_domaine(): void
    {
        $ownerA = User::factory()->withTwoFactor()->create();
        $tenantA = $this->publishableTenant($ownerA, 'organisation-a');
        $eventA = $this->publishedEvent($tenantA);

        $ownerB = User::factory()->withTwoFactor()->create();
        $tenantB = $this->publishableTenant($ownerB, 'organisation-b');

        // Le jeton de A, mais sous le sous-domaine de B : c'est le cloisonnement du scope
        // global qui doit l'empecher de repondre, pas une verification ajoutee au controleur.
        $url = $this->urlFor($tenantB->subdomain, $eventA->public_token);

        $this->get($url)->assertNotFound();
    }

    public function test_un_evenement_cloture_reste_joignable_sur_son_lien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $tenant->asCurrent(function () use ($event) {
            $event->status = EventStatus::Closed;
            $event->save();
        });

        // « Un evenement en cours garde son lien : il affiche l'etat, pas un formulaire. »
        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('event.acceptsRegistrations', false));
    }

    public function test_les_places_restantes_et_le_tarif_sont_exposes_quand_l_organisateur_l_a_choisi(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, [
            'table_count' => 5,
            'seats_per_table' => 4,
            'price_per_person' => 25000,
            'rule_show_remaining_seats' => true,
        ]);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.remainingSeats', 20)
                ->where('event.capacity', 20)
                ->where('event.isFull', false)
                ->where('event.pricePerPerson', 25000),
            );
    }

    public function test_par_defaut_le_nombre_de_places_restantes_n_est_pas_envoye(): void
    {
        // Masquer a l'ecran ne suffit pas : le chiffre resterait lisible dans les donnees de la
        // page. Le serveur ne l'envoie pas du tout.
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['table_count' => 5, 'seats_per_table' => 4]);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.showRemainingSeats', false)
                ->where('event.remainingSeats', null)
                ->where('event.isFull', false),
            );
    }

    public function test_un_evenement_complet_reste_signale_meme_places_masquees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['table_count' => 0, 'seats_per_table' => 0]);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.remainingSeats', null)
                ->where('event.isFull', true),
            );
    }

    public function test_un_evenement_apres_sa_date_limite_l_indique(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['registration_deadline' => now()->subDay()]);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('event.registrationDeadlineHasPassed', true)
                ->where('event.acceptsRegistrations', false),
            );
    }

    public function test_la_marque_de_l_organisation_est_exposee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->get($this->publicUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.colors.primary', '#7b1e3a')
                ->where('tenant.colors.secondary', '#c9a227'),
            );
    }

    public function test_le_lien_public_est_limite_en_debit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $url = $this->publicUrl($tenant, $event);

        for ($i = 0; $i < 20; $i++) {
            $this->get($url)->assertOk();
        }

        // Vingt requetes par minute par adresse IP (CLAUDE.md) : la vingt et unieme est
        // refusee.
        $this->get($url)->assertStatus(429);
    }
}
