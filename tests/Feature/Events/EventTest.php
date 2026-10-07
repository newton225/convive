<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\LegalForm;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Support\GettingStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diner de gala 2026',
            'subtitle' => 'Vingt ans de la communaute',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'venue' => 'Hotel Ivoire',
            'venue_address' => 'Boulevard Hassan II, Cocody',
            'table_groups' => [['count' => 20, 'seats' => 10]],
            'price_per_person' => 15000,
            'companion_limit' => 5,
            'registration_deadline' => now()->addWeeks(3)->toDateTimeString(),
            'purge_at' => now()->addWeeks(3)->addDay()->toDateTimeString(),
            'invitations_send_at' => now()->addWeeks(3)->addDays(2)->toDateTimeString(),
            'hold_duration_minutes' => 10,
            'payment_accounts' => [],
        ], $overrides);
    }

    /**
     * Une organisation reellement publiable : identite legale complete, sous-domaine, et un
     * compte de versement dont le delai d'activation est passe.
     */
    private function publishableTenant(User $owner): Tenant
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

        $tenant->update(['subdomain' => 'convive-ci']);

        $this->visibleAccount($tenant);

        return $tenant->fresh();
    }

    /**
     * Un evenement reellement publiable : capacite, date, et un compte de versement visible
     * **rattache a l'evenement**. Un evenement qui n'offre aucun compte ne dit pas a l'invite
     * ou verser.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function publishableEvent(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->create($attributes);
            $event->paymentAccounts()->sync(
                PaymentAccount::publiclyVisible()->pluck('id')->all(),
            );

            return $event->fresh();
        });
    }

    private function eventOf(Tenant $tenant): Event
    {
        return $tenant->asCurrent(fn () => Event::latest('id')->firstOrFail());
    }

    private function visibleAccount(Tenant $tenant): PaymentAccount
    {
        return $tenant->asCurrent(fn () => PaymentAccount::factory()->create());
    }

    public function test_le_proprietaire_cree_un_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload())
            ->assertRedirect();

        $event = $this->eventOf($tenant);

        $this->assertSame('Diner de gala 2026', $event->name);
        $this->assertSame(EventStatus::Draft, $event->status);
    }

    public function test_un_evenement_nait_en_brouillon_et_sans_lien_public(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)->post(route('tenants.events.store', $tenant), $this->payload());

        $event = $this->eventOf($tenant);

        $this->assertFalse($event->isPublished());
        $this->assertNull($event->public_token);
    }

    public function test_la_capacite_vaut_tables_fois_places_par_table(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)->post(route('tenants.events.store', $tenant), $this->payload([
            'table_groups' => [['count' => 12, 'seats' => 8]],
        ]));

        // La capacite se lit sur les tables du plan de salle, dans la base de l'organisation.
        $this->assertSame(96, $tenant->asCurrent(fn () => Event::firstOrFail()->capacity()));
    }

    public function test_le_montant_du_suit_le_nombre_d_accompagnateurs(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());
        $event = $tenant->asCurrent(fn () => Event::factory()->create(['price_per_person' => 15000]));

        $this->assertSame(15000, $event->amountFor(0));
        $this->assertSame(60000, $event->amountFor(3));
    }

    public function test_le_plafond_d_accompagnateurs_ne_depasse_pas_dix(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload(['companion_limit' => 11]))
            ->assertSessionHasErrors('companion_limit');
    }

    public function test_une_date_limite_posterieure_a_l_evenement_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => now()->addMonth()->toDateTimeString(),
                'registration_deadline' => now()->addMonth()->addDay()->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('registration_deadline');
    }

    public function test_un_compte_de_versement_d_un_autre_locataire_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');
        $foreign = $this->visibleAccount($other);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'payment_accounts' => [$foreign->id],
            ]))
            ->assertSessionHasErrors('payment_accounts.0');
    }

    public function test_un_evenement_sans_capacite_n_est_pas_publiable(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->update(['subdomain' => 'convive-ci']);

        $event = $tenant->asCurrent(fn () => Event::factory()->create([
            'tables' => [0, 0],
        ]));

        $this->assertFalse($event->isReadyToPublish());
    }

    public function test_un_evenement_sans_compte_de_versement_visible_n_est_pas_publiable(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        // Un compte tout juste cree n'a pas encore de valeur vivante : il ne compte pas.
        $tenant->asCurrent(fn () => PaymentAccount::factory()->neverActivated()->create());

        $this->assertFalse($tenant->asCurrent(fn () => $event->fresh())->isReadyToPublish());
    }

    public function test_la_publication_donne_un_jeton_aleatoire_et_non_devinable(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.publish', [$tenant, $event]))
            ->assertRedirect();

        $event = $tenant->asCurrent(fn () => $event->fresh());

        $this->assertNotNull($event->public_token);
        $this->assertGreaterThanOrEqual(64, strlen($event->public_token));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]+$/', $event->public_token);

        // Aucun identifiant sequentiel devinable dans une URL publique : deux evenements
        // n'ont rien en commun dans leur adresse.
        $second = $this->publishableEvent($tenant);
        $this->actingAs($owner)->post(route('tenants.events.publish', [$tenant, $second]));

        $this->assertNotSame($event->public_token, $tenant->asCurrent(fn () => $second->fresh())->public_token);
    }

    public function test_la_publication_ouvre_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.publish', [$tenant, $event]));

        $tenant->asCurrent(function () use ($event) {
            $this->assertSame(EventStatus::Open, $event->fresh()->status);
            $this->assertTrue($event->fresh()->isPublished());
        });
    }

    public function test_le_jeton_public_ne_change_pas_a_la_republication(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant, ['public_token' => bin2hex(random_bytes(32)), 'published_at' => now()]);

        $token = $event->public_token;

        $this->actingAs($owner)->post(route('tenants.events.publish', [$tenant, $event]));

        // Le jeton est l'adresse de l'evenement pour tous ceux qui l'ont recue : il ne tourne pas.
        $this->assertSame($token, $tenant->asCurrent(fn () => $event->fresh())->public_token);
    }

    public function test_un_evenement_publie_ne_se_supprime_pas(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->actingAs($owner)
            ->delete(route('tenants.events.destroy', [$tenant, $event]))
            ->assertForbidden();

        $tenant->asCurrent(fn () => $this->assertNotSoftDeleted($event));
    }

    public function test_un_brouillon_se_supprime(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($owner)
            ->delete(route('tenants.events.destroy', [$tenant, $event]))
            ->assertRedirect();

        $tenant->asCurrent(fn () => $this->assertSoftDeleted($event));
    }

    public function test_la_duplication_ne_reprend_pas_le_lien_public(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->actingAs($owner)
            ->post(route('tenants.events.duplicate', [$tenant, $event]))
            ->assertRedirect();

        $copy = $this->eventOf($tenant);

        $this->assertNotSame($event->id, $copy->id);
        $this->assertNull($copy->public_token);
        $this->assertFalse($copy->isPublished());
        $this->assertSame(EventStatus::Draft, $copy->status);
    }

    public function test_la_duplication_ne_reprend_ni_l_annonce_ni_la_cle_des_billets_ni_les_alertes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $event->ensureSigningKeyPair();
            $event->announced_at = now();
            $event->seats_low_alerted_at = now();
            $event->purge_notice_sent_at = now();
            $event->save();

            return $event;
        });

        $this->actingAs($owner)->post(route('tenants.events.duplicate', [$tenant, $event]));

        $copy = $tenant->asCurrent(fn () => Event::whereKeyNot($event->id)->latest('id')->firstOrFail());

        $this->assertNull($copy->announced_at);
        $this->assertNull($copy->qr_public_key);
        $this->assertSame(1, $copy->qr_key_version);
        $this->assertNull($copy->seats_low_alerted_at);
        $this->assertNull($copy->purge_notice_sent_at);
    }

    public function test_la_cloture_ferme_les_inscriptions(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->actingAs($owner)
            ->post(route('tenants.events.close', [$tenant, $event]))
            ->assertRedirect();

        $event = $tenant->asCurrent(fn () => $event->fresh());

        $this->assertSame(EventStatus::Closed, $event->status);
        $this->assertFalse($event->status->acceptsRegistrations());
    }

    public function test_le_sous_domaine_se_fige_une_fois_un_lien_distribue(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->update(['subdomain' => 'convive-ci']);

        $tenant->asCurrent(fn () => Event::factory()->published()->create());

        // Changer le sous-domaine casserait des adresses deja entre les mains des invites.
        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'autre-nom'])
            ->assertSessionHasErrors('subdomain');

        $this->assertSame('convive-ci', $tenant->fresh()->subdomain);
    }

    public function test_le_sous_domaine_reste_modifiable_tant_que_rien_n_est_publie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->update(['subdomain' => 'convive-ci']);

        $tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($owner)
            ->patch(route('tenants.organisation.subdomain', $tenant), ['subdomain' => 'autre-nom'])
            ->assertRedirect();

        $this->assertSame('autre-nom', $tenant->fresh()->subdomain);
    }

    public function test_les_evenements_d_un_autre_locataire_ne_sont_pas_visibles(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $tenant->asCurrent(fn () => Event::factory()->create());

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');
        $other->asCurrent(fn () => Event::factory()->create());

        $this->assertSame(1, $tenant->asCurrent(fn () => Event::count()));
    }

    public function test_un_locataire_tiers_recoit_404_sur_la_liste(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.events.index', $tenant))
            ->assertNotFound();
    }

    public function test_un_evenement_d_un_autre_locataire_ne_peut_pas_etre_modifie(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $other = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create(), 'Autre');
        $foreign = $other->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($owner)
            ->patch(route('tenants.events.update', [$tenant, $foreign]), $this->payload())
            ->assertNotFound();
    }

    public function test_sans_la_permission_de_creation_un_evenement_n_est_pas_cree(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.store', $tenant), $this->payload())
            ->assertForbidden();
    }

    public function test_sans_la_permission_de_cloture_un_evenement_n_est_pas_cloture(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create());

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [
            TenantPermission::EventsView,
            TenantPermission::EventsUpdate,
        ]);

        $this->actingAs($member)
            ->post(route('tenants.events.close', [$tenant, $event]))
            ->assertForbidden();
    }

    public function test_la_creation_d_un_evenement_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)->post(route('tenants.events.store', $tenant), $this->payload());

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'event.created')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('Diner de gala 2026', $activity->properties['attributes']['name']);
    }

    public function test_la_publication_est_journalisee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.publish', [$tenant, $event]));

        $this->assertNotNull($tenant->asCurrent(fn () => Activity::where('description', 'event.published')->latest('id')->first()));
    }

    public function test_un_evenement_qui_n_est_pas_pret_ne_se_publie_pas(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        // L'organisation n'a ni identite legale complete, ni sous-domaine, ni compte de
        // versement visible : un recu emis dans cet etat n'aurait aucune valeur.
        $this->actingAs($owner)
            ->post(route('tenants.events.publish', [$tenant, $event]))
            ->assertSessionHasErrors('event');

        $this->assertFalse($tenant->asCurrent(fn () => $event->fresh())->isPublished());
    }

    public function test_les_places_restantes_valent_la_capacite_sans_inscription(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        // Sans inscription confirmee ou en cours (etape 5), ce calcul porte sur zero et les
        // places restantes valent la capacite.
        $tenant->asCurrent(function () {
            $event = Event::factory()->create(['tables' => [10, 8]]);

            $this->assertSame(80, $event->remainingSeats());
            $this->assertFalse($event->isFull());
        });
    }

    public function test_un_evenement_sans_capacite_est_complet(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $tenant->asCurrent(function () {
            $event = Event::factory()->create(['tables' => [0, 0]]);

            $this->assertSame(0, $event->remainingSeats());
            $this->assertTrue($event->isFull());
        });
    }

    public function test_un_evenement_ouvert_accepte_les_inscriptions(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create(['registration_deadline' => now()->addWeek()]);

            $this->assertTrue($event->acceptsRegistrations());
            $this->assertFalse($event->registrationDeadlineHasPassed());
        });
    }

    public function test_un_evenement_apres_sa_date_limite_n_accepte_plus_les_inscriptions(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create(['registration_deadline' => now()->subDay()]);

            $this->assertTrue($event->registrationDeadlineHasPassed());
            $this->assertFalse($event->acceptsRegistrations());
        });
    }

    public function test_un_brouillon_n_accepte_pas_les_inscriptions_meme_sans_date_limite(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $event = $tenant->asCurrent(fn () => Event::factory()->create([
            'registration_deadline' => null,
        ]));

        $this->assertFalse($event->acceptsRegistrations());
    }

    public function test_un_evenement_complet_n_accepte_plus_les_inscriptions(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create([
                'tables' => [0, 0],
                'registration_deadline' => now()->addWeek(),
            ]);

            $this->assertTrue($event->isFull());
            $this->assertFalse($event->acceptsRegistrations());
        });
    }

    public function test_l_adresse_publique_vit_sous_le_sous_domaine_de_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.publish', [$tenant, $event]));

        $tenant->asCurrent(function () use ($event) {
            $event = $event->fresh();

            $this->assertNotNull($event->publicUrl());
            $this->assertStringContainsString('convive-ci.', $event->publicUrl());
            $this->assertStringContainsString('/e/'.$event->public_token, $event->publicUrl());
        });
    }

    public function test_l_adresse_publique_est_absente_sans_sous_domaine(): void
    {
        $tenant = $this->tenantOwnedBy(User::factory()->withTwoFactor()->create());

        $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();

            $this->assertNull($event->publicUrl());
        });
    }

    public function test_les_couleurs_de_l_evenement_sont_enregistrees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)->post(route('tenants.events.store', $tenant), $this->payload([
            'primary_color' => '#112233',
            'secondary_color' => '#aabbcc',
        ]));

        $event = $this->eventOf($tenant);

        $this->assertSame('#112233', $event->primary_color);
        $this->assertSame('#aabbcc', $event->secondary_color);
    }

    public function test_une_couleur_mal_formee_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'primary_color' => 'bleu',
            ]))
            ->assertSessionHasErrors('primary_color');
    }

    public function test_un_evenement_sans_couleur_retombe_sur_celles_de_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(function () {
            $tenant = Tenant::current();
            $tenant->brandingOrCreate()->fill([
                'primary_color' => '#654321',
                'secondary_color' => '#123456',
            ])->save();

            $event = Event::factory()->create(['primary_color' => null, 'secondary_color' => null]);

            $this->assertSame(['primary' => '#654321', 'secondary' => '#123456'], $event->colors());
        });
    }

    public function test_les_couleurs_propres_a_l_evenement_priment_sur_celles_de_l_organisation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $tenant->asCurrent(function () {
            Tenant::current()->brandingOrCreate()->fill([
                'primary_color' => '#654321',
                'secondary_color' => '#123456',
            ])->save();

            $event = Event::factory()->create([
                'primary_color' => '#00ff00',
                'secondary_color' => '#ff00ff',
            ]);

            $this->assertSame(['primary' => '#00ff00', 'secondary' => '#ff00ff'], $event->colors());
        });
    }

    public function test_creer_un_evenement_depuis_les_premiers_pas_ramene_au_tableau_de_bord(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.events.store', [$tenant, ...GettingStarted::ReturnQuery]), $this->payload())
            ->assertRedirect(route('dashboard', $tenant));
    }

    public function test_publier_depuis_les_premiers_pas_ramene_au_tableau_de_bord(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.publish', [$tenant, $event, ...GettingStarted::ReturnQuery]))
            ->assertRedirect(route('dashboard', $tenant));
    }

    public function test_publier_hors_des_premiers_pas_garde_la_fiche_de_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishableEvent($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.publish', [$tenant, $event]))
            ->assertRedirect(route('tenants.events.edit', [$tenant, $event]));
    }
}
