<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\EventStatus;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Enums\TicketModel;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
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

    private function urlFor(string $subdomain, string $token, string $suffix = ''): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$token}{$suffix}";
    }

    private function registrationFormUrl(Tenant $tenant, Event $event): string
    {
        return $this->urlFor($tenant->subdomain, $event->public_token, '/register');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Tenant $tenant): array
    {
        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        return [
            'name' => 'Aya Kouassi',
            'phone' => '+225 07 07 12 34 56',
            'unit_id' => $unitId,
            'companions' => [],
        ];
    }

    public function test_le_formulaire_s_affiche_pour_un_evenement_qui_accepte_les_inscriptions(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['name' => 'Diner de gala 2026']);

        $this->get($this->registrationFormUrl($tenant, $event))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration')
                ->where('event.name', 'Diner de gala 2026')
                ->has('units'),
            );
    }

    public function test_le_formulaire_redirige_vers_l_evenement_quand_les_inscriptions_sont_fermees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $tenant->asCurrent(function () use ($event) {
            $event->status = EventStatus::Closed;
            $event->save();
        });

        $this->get($this->registrationFormUrl($tenant, $event))
            ->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token));
    }

    public function test_un_jeton_inconnu_recoit_404_sur_le_formulaire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);

        $this->get($this->urlFor($tenant->subdomain, str_repeat('a', 64), '/register'))
            ->assertNotFound();
    }

    public function test_une_inscription_valide_est_immediatement_reservee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['price_per_person' => 15000]);

        $this->post($this->registrationFormUrl($tenant, $event), $this->validPayload($tenant))
            ->assertRedirect();

        $registration = $tenant->asCurrent(fn () => Registration::first());

        // README 2.2 : toute nouvelle inscription revérifie le stock et demarre son decompte
        // avant meme d'afficher l'ecran de paiement.
        $this->assertNotNull($registration);
        $this->assertSame(RegistrationStatus::Held, $registration->status);
        $this->assertNotNull($registration->held_until);
        $this->assertSame(1, $registration->hold_sequence);
        $this->assertSame('Aya Kouassi', $registration->name);
        $this->assertSame(15000, $registration->amount_due);
    }

    public function test_le_montant_du_est_le_tarif_multiplie_par_le_nombre_de_personnes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['price_per_person' => 15000]);

        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        $payload = $this->validPayload($tenant);
        $payload['companions'] = [
            ['name' => 'Kofi Diallo', 'unit_id' => $unitId],
            ['name' => 'Awa Toure', 'unit_id' => $unitId],
        ];

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertRedirect();

        $registration = $tenant->asCurrent(fn () => Registration::first());

        // Tarif par personne x (1 + accompagnateurs) : README 2.5.
        $this->assertSame(45000, $registration->amount_due);
        $this->assertSame(2, $tenant->asCurrent(fn () => $registration->companions()->count()));
    }

    public function test_le_nom_et_le_telephone_sont_obligatoires(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $payload = $this->validPayload($tenant);
        unset($payload['name'], $payload['phone']);

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors(['name', 'phone']);

        $this->assertSame(0, $tenant->asCurrent(fn () => Registration::count()));
    }

    /**
     * README 2.5 : l'email est facultatif, a l'inverse du telephone.
     */
    public function test_l_email_est_facultatif_mais_enregistre_quand_fourni(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $payload = $this->validPayload($tenant);
        $payload['email'] = 'aya@example.com';

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertRedirect();

        $registration = $tenant->asCurrent(fn () => Registration::first());

        $this->assertSame('aya@example.com', $registration->email);
    }

    public function test_un_email_mal_forme_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $payload = $this->validPayload($tenant);
        $payload['email'] = 'pas-un-email';

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors(['email']);

        $this->assertSame(0, $tenant->asCurrent(fn () => Registration::count()));
    }

    public function test_l_unite_est_obligatoire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $payload = $this->validPayload($tenant);
        unset($payload['unit_id']);

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors('unit_id');
    }

    public function test_aucune_est_un_choix_valide_pour_l_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $noneId = $tenant->asCurrent(fn () => Unit::where('name', Unit::None)->value('id'));

        $payload = $this->validPayload($tenant);
        $payload['unit_id'] = $noneId;

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    public function test_une_unite_desactivee_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $inactiveId = $tenant->asCurrent(function () {
            $unit = Unit::where('name', 'QODESH')->first();
            $unit->update(['is_active' => false]);

            return $unit->id;
        });

        $payload = $this->validPayload($tenant);
        $payload['unit_id'] = $inactiveId;

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors('unit_id');
    }

    public function test_chaque_accompagnateur_doit_avoir_un_nom_et_une_unite(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $payload = $this->validPayload($tenant);
        $payload['companions'] = [['name' => '', 'unit_id' => null]];

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors(['companions.0.name', 'companions.0.unit_id']);
    }

    public function test_le_nombre_d_accompagnateurs_ne_peut_pas_depasser_le_plafond_de_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['companion_limit' => 1]);

        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        $payload = $this->validPayload($tenant);
        $payload['companions'] = [
            ['name' => 'Kofi Diallo', 'unit_id' => $unitId],
            ['name' => 'Awa Toure', 'unit_id' => $unitId],
        ];

        $this->post($this->registrationFormUrl($tenant, $event), $payload)
            ->assertSessionHasErrors('companions');

        $this->assertSame(0, $tenant->asCurrent(fn () => Registration::count()));
    }

    public function test_la_reservation_affiche_le_decompte_et_le_recapitulatif(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['price_per_person' => 15000]);

        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        $payload = $this->validPayload($tenant);
        $payload['companions'] = [['name' => 'Kofi Diallo', 'unit_id' => $unitId]];

        $response = $this->post($this->registrationFormUrl($tenant, $event), $payload);
        $response->assertRedirect();

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration-show')
                ->where('registration.name', 'Aya Kouassi')
                ->where('registration.status', 'held')
                ->where('registration.amountDue', 30000)
                ->where('registration.companions.0.name', 'Kofi Diallo')
                ->has('registration.heldUntil')
                ->has('paymentAccounts'),
            );
    }

    /**
     * README ecran 9 : le recapitulatif de reprise affiche les places encore libres, pour que
     * l'invite ne relance pas a l'aveugle une reservation expiree ou une preuve rejetee.
     */
    public function test_la_reservation_affiche_les_places_encore_libres(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['table_count' => 5, 'seats_per_table' => 10]);

        $payload = $this->validPayload($tenant);
        $response = $this->post($this->registrationFormUrl($tenant, $event), $payload);

        $this->get($response->headers->get('Location'))
            ->assertInertia(fn ($page) => $page->where('event.remainingSeats', 49));
    }

    public function test_le_jeton_de_reprise_n_est_pas_l_identifiant_sequentiel_de_l_inscription(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $response = $this->post($this->registrationFormUrl($tenant, $event), $this->validPayload($tenant));
        $response->assertRedirect();

        $registration = $tenant->asCurrent(fn () => Registration::first());
        $location = $response->headers->get('Location');

        $this->assertStringContainsString('/register/', $location);

        $resumeToken = last(explode('/', rtrim((string) $location, '/')));

        // CLAUDE.md, Securite : aucun identifiant sequentiel devinable dans une URL publique.
        // Compare le segment entier a l'identifiant, pas une simple sous-chaine : un jeton
        // aleatoire qui commencerait par le meme chiffre ferait echouer un `assertStringNotContainsString`
        // sans rien prouver de faux.
        $this->assertNotSame((string) $registration->id, $resumeToken);
        $this->assertGreaterThanOrEqual(64, strlen($resumeToken));
    }

    public function test_la_reservation_d_une_autre_inscription_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $eventA = $this->publishedEvent($tenant, ['name' => 'Evenement A']);
        $eventB = $this->publishedEvent($tenant, ['name' => 'Evenement B']);

        $plainToken = Registration::generateResumeToken();
        $tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $eventB->id,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        $this->get($this->urlFor($tenant->subdomain, $eventA->public_token, "/register/{$plainToken}"))
            ->assertNotFound();
    }

    public function test_un_jeton_de_reprise_inconnu_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, '/register/'.str_repeat('a', 64)))
            ->assertNotFound();
    }

    public function test_la_page_de_reservation_marque_expiree_une_reservation_dont_le_decompte_est_ecoule(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $plainToken = Registration::generateResumeToken();
        $tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Held,
            'held_until' => now()->subMinute(),
            'hold_sequence' => 1,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('registration.status', 'expired'));

        $registration = $tenant->asCurrent(fn () => Registration::first());
        $this->assertSame(RegistrationStatus::Expired, $registration->status);
    }

    public function test_relancer_une_reservation_expiree_la_remet_en_attente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $plainToken = Registration::generateResumeToken();
        $tenant->asCurrent(fn () => Registration::factory()->expired()->create([
            'event_id' => $event->id,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        $this->post($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}/retry"))
            ->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}"));

        $registration = $tenant->asCurrent(fn () => Registration::first());
        $this->assertSame(RegistrationStatus::Held, $registration->status);
        $this->assertTrue($registration->held_until->isFuture());
    }

    public function test_relancer_quand_l_evenement_est_complet_redirige_vers_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['table_count' => 1, 'seats_per_table' => 1]);

        $plainToken = Registration::generateResumeToken();
        $expired = $tenant->asCurrent(fn () => Registration::factory()->expired()->create([
            'event_id' => $event->id,
            'party_size' => 1,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        // Une autre inscription occupe deja la seule place disponible.
        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => 1,
        ]));

        $this->post($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}/retry"))
            ->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token));

        $this->assertSame(
            RegistrationStatus::Expired,
            $tenant->asCurrent(fn () => $expired->fresh())->status,
        );
    }

    /**
     * Etape 9 : une inscription annulee par l'organisation reste consultable (la ligne n'est
     * pas supprimee comme le serait une purge), mais l'invite ne doit plus voir le formulaire de
     * paiement pour un dossier que l'organisation a clos elle-meme.
     */
    public function test_une_inscription_annulee_affiche_un_message_plutot_que_le_paiement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $plainToken = Registration::generateResumeToken();
        $tenant->asCurrent(fn () => Registration::factory()->cancelled()->create([
            'event_id' => $event->id,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('registration.status', 'cancelled'));
    }

    /**
     * Une inscription annulee par l'organisation ne doit pas pouvoir etre relancee par l'invite
     * lui-meme : `retry()` ne whiteliste que Held/Expired/ProofRejected (README 2.2).
     */
    public function test_relancer_une_inscription_annulee_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $plainToken = Registration::generateResumeToken();
        $tenant->asCurrent(fn () => Registration::factory()->cancelled()->create([
            'event_id' => $event->id,
            'resume_token_hash' => Registration::hashResumeToken($plainToken),
        ]));

        $this->post($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}/retry"))
            ->assertNotFound();
    }

    /**
     * README ecran 7, etape 7 de « Ordre de construction » : le billet s'affiche des que
     * l'inscription est confirmee.
     */
    public function test_le_billet_s_affiche_pour_une_inscription_confirmee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);
        $plainToken = Registration::generateResumeToken();

        $tenant->asCurrent(function () use ($event, $plainToken) {
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'resume_token_hash' => Registration::hashResumeToken($plainToken),
            ]);

            app(IssueTicket::class)->handle($registration);
        });

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('registration.status', 'confirmed')
                ->where('registration.ticket.tableNumber', null)
                ->has('registration.ticket.qrImage'),
            );
    }

    /**
     * README ecran 15 : le gabarit du billet (modele, elements activables) est un reglage
     * d'organisation, applique ici au billet reellement remis a l'invite, pas seulement a
     * l'apercu du back-office (`App\Http\Controllers\Tenants\TicketTemplateController`).
     */
    public function test_le_billet_applique_le_gabarit_enregistre_par_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $tenant->brandingOrCreate()->update([
            'ticket_model' => TicketModel::Sober,
            'ticket_element_companions' => false,
        ]);
        $event = $this->publishedEvent($tenant);
        $plainToken = Registration::generateResumeToken();

        $tenant->asCurrent(function () use ($event, $plainToken) {
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'resume_token_hash' => Registration::hashResumeToken($plainToken),
            ]);

            app(IssueTicket::class)->handle($registration);
        });

        $this->get($this->urlFor($tenant->subdomain, $event->public_token, "/register/{$plainToken}"))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('registration.ticket.model', 'sober')
                ->where('registration.ticket.elements.companions', false)
                ->where('registration.ticket.elements.logo', true),
            );
    }

    /**
     * README 2.7, etape 8 : le lien signe des envois programmes donne acces a la meme page
     * qu'un jeton de reprise, sans jamais reconstituer ce dernier (CLAUDE.md, « Securite »).
     */
    public function test_le_lien_signe_affiche_la_reservation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));
        $link = $tenant->asCurrent(fn () => $registration->fresh()->signedResumeUrl());

        $this->get($link)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('registration.status', 'confirmed'));
    }

    public function test_un_lien_signe_altere_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $registration = $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create(['event_id' => $event->id]));
        $link = $tenant->asCurrent(fn () => $registration->fresh()->signedResumeUrl());

        $this->get($link.'-tampered')->assertNotFound();
    }

    public function test_le_lien_signe_d_une_autre_inscription_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        [$registration, $other] = $tenant->asCurrent(fn () => [
            Registration::factory()->confirmed()->create(['event_id' => $event->id]),
            Registration::factory()->confirmed()->create(['event_id' => $event->id]),
        ]);

        $signature = $tenant->asCurrent(fn () => $registration->fresh()->notificationToken());

        $this->get($this->urlFor(
            $tenant->subdomain,
            $event->public_token,
            "/register/{$other->id}/link?signature={$signature}",
        ))->assertNotFound();
    }

    public function test_le_formulaire_d_inscription_est_limite_en_debit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $url = $this->registrationFormUrl($tenant, $event);

        for ($i = 0; $i < 20; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertStatus(429);
    }
}
