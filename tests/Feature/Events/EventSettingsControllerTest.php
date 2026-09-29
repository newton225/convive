<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les reglages d'un evenement (README ecran 24) : places, echeances, couleurs, rappels et
 * regles, tous reels. Trois regles s'enregistrent sans encore rien gouverner
 * (`unenforcedRules`, voir `EventSettingsController`).
 */
class EventSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create([
            'name' => 'Gala',
            'table_count' => 20,
            'seats_per_table' => 10,
            'hold_duration_minutes' => 15,
        ]));
    }

    public function test_un_membre_avec_la_permission_voit_les_reglages_reels(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.settings.edit', [$this->tenant, $this->event]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('events/settings')
                ->where('event.name', 'Gala')
                ->where('event.capacity', 200)
                ->where('event.holdDurationMinutes', 15)
                ->where('colors.primary', $this->tenant->brandingOrCreate()->colors()['primary'])
                ->where('reminders.d7', true)
                ->where('reminders.d2', true)
                ->where('reminders.d1', true)
                ->where('reminders.dayOf', true)
                ->where('rules.scheduledSend', true)
                ->where('rules.autoSeating', true)
                ->where('unenforcedRules', ['allowWithoutProof', 'proofLegibility', 'temporaryHold']),
            );
    }

    public function test_l_identite_visuelle_montre_les_couleurs_et_le_visuel_propres_a_l_evenement(): void
    {
        $this->tenant->asCurrent(fn () => $this->event->update(['primary_color' => '#123456']));

        $this->actingAs($this->owner)
            ->get(route('tenants.events.settings.edit', [$this->tenant, $this->event]))
            ->assertInertia(fn ($page) => $page
                ->where('colors.primary', '#123456')
                ->where('colors.secondary', $this->tenant->brandingOrCreate()->colors()['secondary'])
                ->where('colorsOverridden', true)
                ->where('event.visualUrl', null),
            );
    }

    public function test_sans_couleurs_propres_l_evenement_reprend_la_marque_de_l_organisation(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.settings.edit', [$this->tenant, $this->event]))
            ->assertInertia(fn ($page) => $page->where('colorsOverridden', false));
    }

    public function test_un_membre_avec_la_permission_enregistre_les_reglages(): void
    {
        $this->actingAs($this->owner)
            ->patch(route('tenants.events.settings.update', [$this->tenant, $this->event]), [
                'reminder_j7_enabled' => false,
                'reminder_j2_enabled' => true,
                'reminder_j1_enabled' => true,
                'reminder_day_of_enabled' => true,
                'rule_scheduled_send' => false,
                'rule_auto_seating' => true,
                'rule_allow_without_proof' => true,
                'rule_proof_legibility' => true,
                'rule_purge_on_exhaustion' => true,
                'rule_temporary_hold' => true,
            ])
            ->assertRedirect(route('tenants.events.settings.edit', [$this->tenant, $this->event]));

        $event = $this->tenant->asCurrent(fn () => $this->event->fresh());

        $this->assertFalse($event->reminder_j7_enabled);
        $this->assertFalse($event->rule_scheduled_send);
        $this->assertTrue($event->rule_auto_seating);
    }

    public function test_les_places_restantes_sont_masquees_par_defaut_et_s_affichent_sur_demande(): void
    {
        $this->assertFalse($this->tenant->asCurrent(fn () => $this->event->fresh()->rule_show_remaining_seats));

        $this->actingAs($this->owner)
            ->patch(route('tenants.events.settings.update', [$this->tenant, $this->event]), [
                'rule_show_remaining_seats' => true,
            ])
            ->assertRedirect();

        $this->assertTrue($this->tenant->asCurrent(fn () => $this->event->fresh()->rule_show_remaining_seats));
    }

    public function test_une_case_decochee_absente_de_la_requete_vaut_faux(): void
    {
        // Une case a cocher HTML decochee n'envoie aucune valeur : le controleur doit lire
        // l'absence comme faux, pas la rejeter ni la laisser a sa valeur precedente.
        $this->actingAs($this->owner)
            ->patch(route('tenants.events.settings.update', [$this->tenant, $this->event]), [])
            ->assertRedirect();

        $event = $this->tenant->asCurrent(fn () => $this->event->fresh());

        $this->assertFalse($event->rule_scheduled_send);
        $this->assertFalse($event->reminder_j7_enabled);
    }

    public function test_un_membre_sans_la_permission_n_enregistre_rien(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->patch(route('tenants.events.settings.update', [$this->tenant, $this->event]), [
                'rule_scheduled_send' => false,
            ])
            ->assertForbidden();

        $event = $this->tenant->asCurrent(fn () => $this->event->fresh());

        $this->assertTrue($event->rule_scheduled_send);
    }

    public function test_un_membre_sans_la_permission_de_modification_ne_voit_pas_les_reglages(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->get(route('tenants.events.settings.edit', [$this->tenant, $this->event]))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_les_reglages(): void
    {
        $this->actingAs(User::factory()->withTwoFactor()->create())
            ->get(route('tenants.events.settings.edit', [$this->tenant, $this->event]))
            ->assertNotFound();
    }

    public function test_un_evenement_inexistant_recoit_404(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.settings.edit', [$this->tenant, 99999]))
            ->assertNotFound();
    }
}
