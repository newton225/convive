<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Date et heure de fin d'un evenement : facultatives, avec repli sur le debut (decision du
 * 2026-10-09). Sans fin, le billet vaut jusqu'a 24 heures apres le debut.
 */
class EventEndTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diner de gala 2026',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'table_groups' => [['count' => 20, 'seats' => 10]],
            'price_per_person' => 15000,
            'price_categories' => [['name' => 'Standard', 'price' => 15000, 'quota' => 50]],
            'payment_accounts' => [],
        ], $overrides);
    }

    public function test_sans_fin_le_billet_vaut_vingt_quatre_heures_apres_le_debut(): void
    {
        $startsAt = now()->addWeek()->startOfMinute();
        $event = new Event(['starts_at' => $startsAt, 'entry_grace_minutes' => 0]);

        $this->assertEquals($startsAt->addHours(24)->getTimestamp(), $event->ticketValidUntil()->getTimestamp());
    }

    public function test_avec_une_fin_le_billet_vaut_jusqu_a_cette_fin(): void
    {
        $startsAt = now()->addWeek()->startOfMinute();
        $event = new Event(['starts_at' => $startsAt, 'ends_at' => $startsAt->copy()->addHours(5), 'entry_grace_minutes' => 0]);

        $this->assertEquals($startsAt->copy()->addHours(5)->getTimestamp(), $event->ticketValidUntil()->getTimestamp());
    }

    public function test_la_marge_apres_la_fin_vaut_trente_minutes_par_defaut_et_se_regle(): void
    {
        $startsAt = now()->addWeek()->startOfMinute();
        $endsAt = $startsAt->copy()->addHours(5);

        $default = new Event(['starts_at' => $startsAt, 'ends_at' => $endsAt]);
        $custom = new Event(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'entry_grace_minutes' => 90]);

        $this->assertEquals($endsAt->copy()->addMinutes(30)->getTimestamp(), $default->ticketValidUntil()->getTimestamp());
        $this->assertEquals($endsAt->copy()->addMinutes(90)->getTimestamp(), $custom->ticketValidUntil()->getTimestamp());
    }

    public function test_les_portes_n_ont_pas_d_heure_d_ouverture_sauf_reglage(): void
    {
        $startsAt = now()->addWeek()->startOfMinute();

        $this->assertNull((new Event(['starts_at' => $startsAt]))->ticketValidFrom());
        $this->assertEquals(
            $startsAt->copy()->subMinutes(120)->getTimestamp(),
            (new Event(['starts_at' => $startsAt, 'entry_opens_minutes_before' => 120]))->ticketValidFrom()->getTimestamp(),
        );
    }

    public function test_la_fenetre_d_entree_s_enregistre_et_se_relit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'entry_opens_minutes_before' => 90,
                'entry_grace_minutes' => 45,
            ]))
            ->assertSessionHasNoErrors();

        $event = $tenant->asCurrent(fn () => Event::query()->firstOrFail());

        $this->assertSame(90, $event->entry_opens_minutes_before);
        $this->assertSame(45, $event->entry_grace_minutes);
    }

    public function test_une_marge_vide_reprend_la_valeur_par_defaut(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'entry_opens_minutes_before' => '',
                'entry_grace_minutes' => '',
            ]))
            ->assertSessionHasNoErrors();

        $event = $tenant->asCurrent(fn () => Event::query()->firstOrFail());

        $this->assertNull($event->entry_opens_minutes_before);
        $this->assertSame(Event::DefaultEntryGraceMinutes, $event->entry_grace_minutes);
    }

    public function test_la_fenetre_d_entree_a_des_bornes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'entry_opens_minutes_before' => -5,
                'entry_grace_minutes' => 5000,
            ]))
            ->assertSessionHasErrors(['entry_opens_minutes_before', 'entry_grace_minutes']);
    }

    public function test_un_evenement_n_est_termine_qu_a_sa_fin(): void
    {
        $this->assertTrue((new Event(['starts_at' => now()->subHour()]))->hasEnded());
        $this->assertFalse((new Event(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour()]))->hasEnded());
        $this->assertTrue((new Event(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]))->hasEnded());
        $this->assertFalse((new Event(['starts_at' => now()->addDay()]))->hasEnded());
    }

    public function test_la_fin_s_enregistre_et_se_relit(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $startsAt = now()->addMonth()->startOfMinute();

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->addHours(4)->toDateTimeString(),
            ]))
            ->assertSessionHasNoErrors();

        $event = $tenant->asCurrent(fn () => Event::query()->firstOrFail());

        $this->assertEquals($startsAt->copy()->addHours(4)->getTimestamp(), $event->ends_at->getTimestamp());
    }

    public function test_la_fin_est_facultative(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertNull($tenant->asCurrent(fn () => Event::query()->firstOrFail()->ends_at));
    }

    public function test_la_fin_ne_peut_pas_preceder_le_debut(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $startsAt = now()->addMonth();

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->subHour()->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_une_fin_sans_debut_est_refusee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => null,
                'ends_at' => now()->addMonth()->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('ends_at');
    }

    public function test_la_validation_en_temps_reel_signale_l_erreur_du_champ_sans_rien_creer(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $startsAt = now()->addMonth();

        $this->actingAs($owner)
            ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'ends_at'])
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->subHour()->toDateTimeString(),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');

        $this->assertSame(0, $tenant->asCurrent(fn () => Event::query()->count()));
    }

    public function test_la_validation_en_temps_reel_accepte_une_valeur_correcte_sans_rien_creer(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $startsAt = now()->addMonth();

        $this->actingAs($owner)
            ->withHeaders(['Precognition' => 'true', 'Precognition-Validate-Only' => 'ends_at'])
            ->post(route('tenants.events.store', $tenant), $this->payload([
                'starts_at' => $startsAt->toDateTimeString(),
                'ends_at' => $startsAt->copy()->addHours(3)->toDateTimeString(),
            ]))
            ->assertNoContent();

        $this->assertSame(0, $tenant->asCurrent(fn () => Event::query()->count()));
    }

    public function test_un_evenement_publie_peut_avoir_commence_s_il_n_est_pas_termine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create(['starts_at' => now()->addWeek()]));

        $this->actingAs($owner)
            ->patch(route('tenants.events.update', [$tenant, $event]), $this->payload([
                'starts_at' => now()->subHour()->toDateTimeString(),
                'ends_at' => now()->addHours(3)->toDateTimeString(),
            ]))
            ->assertSessionDoesntHaveErrors(['starts_at', 'ends_at']);
    }

    public function test_un_evenement_publie_ne_peut_pas_se_terminer_dans_le_passe(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $event = $tenant->asCurrent(fn () => Event::factory()->published()->create(['starts_at' => now()->addWeek()]));

        $this->actingAs($owner)
            ->patch(route('tenants.events.update', [$tenant, $event]), $this->payload([
                'starts_at' => now()->subHours(5)->toDateTimeString(),
                'ends_at' => now()->subHour()->toDateTimeString(),
            ]))
            ->assertSessionHasErrors('ends_at');
    }
}
