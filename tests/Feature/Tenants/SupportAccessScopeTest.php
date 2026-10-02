<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Models\ConsoleActionLog;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\SupportAccessExtended;
use App\Settings\SupportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * L'acces du support (README ecran 25), decisions du proprietaire du projet le 2026-10-02 : il peut
 * etre limite a un seul evenement, il se prolonge sans etre rouvert, et les durees proposees se
 * reglent depuis la console.
 */
class SupportAccessScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $operator;

    private Tenant $tenant;

    private Event $gala;

    private Event $seminar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        $this->operator = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test', 'support_available' => true]);
        config(['convive.console.operators' => ['support@convive.test']]);

        [$this->gala, $this->seminar] = $this->tenant->asCurrent(fn () => [
            Event::factory()->open()->create(['name' => 'Diner de gala']),
            Event::factory()->open()->create(['name' => 'Seminaire des responsables']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function open(array $payload = []): TestResponse
    {
        return $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tenants.support-access.store', $this->tenant), [
                'operator_id' => $this->operator->id,
                'duration' => 4,
                'reason' => 'Les preuves du diner de gala n apparaissent pas dans la file.',
                ...$payload,
            ]);
    }

    private function grant(?Event $event = null, int $hours = 4): SupportAccessGrant
    {
        return SupportAccessGrant::create([
            'tenant_id' => $this->tenant->id,
            'operator_id' => $this->operator->id,
            'granted_by_id' => $this->owner->id,
            'expires_at' => now()->addHours($hours),
            'event_id' => $event?->id,
            'event_name' => $event?->name,
        ]);
    }

    /**
     * @return TestResponse<Response>
     */
    private function extend(SupportAccessGrant $grant, int $hours, ?User $actor = null): TestResponse
    {
        return $this->actingAs($actor ?? $this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('tenants.support-access.update', [$this->tenant, $grant]), ['duration' => $hours]);
    }

    public function test_un_proprietaire_limite_l_acces_a_un_evenement(): void
    {
        $this->open(['event_id' => $this->gala->id])->assertRedirect();

        $grant = SupportAccessGrant::sole();

        $this->assertSame($this->gala->id, $grant->event_id);
        $this->assertSame('Diner de gala', $grant->event_name);

        $this->tenant->asCurrent(fn () => $this->assertSame(
            'Diner de gala',
            Activity::where('description', 'support_access.opened')->sole()->properties['event'],
        ));
    }

    public function test_sans_evenement_choisi_l_acces_porte_sur_toute_l_organisation(): void
    {
        $this->open()->assertRedirect();

        $this->assertNull(SupportAccessGrant::sole()->event_id);
    }

    public function test_l_evenement_choisi_doit_etre_un_evenement_de_l_organisation(): void
    {
        $this->open(['event_id' => 999999])->assertSessionHasErrors('event_id');

        $this->assertSame(0, SupportAccessGrant::count());
    }

    public function test_un_acces_limite_lit_les_ecrans_de_son_evenement(): void
    {
        $this->grant($this->gala);

        $this->actingAs($this->operator)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->gala]))
            ->assertOk();
        $this->actingAs($this->operator)
            ->get(route('tenants.events.proofs.index', [$this->tenant, $this->gala]))
            ->assertOk();
    }

    public function test_un_acces_limite_ne_lit_aucun_ecran_d_un_autre_evenement(): void
    {
        $this->grant($this->gala);

        foreach (['tenants.events.registrations.index', 'tenants.events.proofs.index', 'tenants.events.seating.index', 'tenants.events.report.show'] as $route) {
            $this->actingAs($this->operator)
                ->get(route($route, [$this->tenant, $this->seminar]))
                ->assertNotFound();
        }
    }

    public function test_un_acces_limite_ne_voit_que_son_evenement_dans_la_liste(): void
    {
        $this->grant($this->gala);

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('events', 1)
                ->where('events.0.id', $this->gala->id),
            );
    }

    public function test_un_acces_limite_est_ramene_a_la_liste_depuis_les_ecrans_de_toute_l_organisation(): void
    {
        $this->grant($this->gala);

        // Tableau de bord, historique, equipe : ils parlent de toute l'organisation.
        foreach ([route('dashboard', $this->tenant), route('tenants.audit.index', $this->tenant), route('tenants.edit', $this->tenant)] as $url) {
            $this->actingAs($this->operator)
                ->get($url)
                ->assertRedirect(route('tenants.events.index', $this->tenant));
        }
    }

    public function test_un_acces_limite_n_ouvre_pas_le_recu_d_une_preuve_d_un_autre_evenement(): void
    {
        $this->grant($this->gala);

        $proof = $this->tenant->asCurrent(function () {
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $this->seminar->id]);

            return PaymentProof::factory()->create(['registration_id' => $registration->id]);
        });

        // Meme en passant par l'adresse de l'evenement autorise.
        $this->actingAs($this->operator)
            ->get(route('tenants.events.proofs.receipt', [$this->tenant, $this->gala, $proof]))
            ->assertNotFound();
    }

    public function test_un_acces_sur_toute_l_organisation_lit_tous_les_evenements_comme_avant(): void
    {
        $this->grant();

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->has('events', 2));
        $this->actingAs($this->operator)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->seminar]))
            ->assertOk();
        $this->actingAs($this->operator)
            ->get(route('dashboard', $this->tenant))
            ->assertOk();
    }

    public function test_le_bandeau_et_l_ecran_disent_a_quel_evenement_l_acces_est_limite(): void
    {
        $this->grant($this->gala);

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeAccess.event', 'Diner de gala')
                ->where('supportAccess.event', 'Diner de gala')
                ->has('events', 2),
            );
    }

    public function test_un_proprietaire_prolonge_l_acces_en_cours(): void
    {
        $this->freezeSecond();
        Notification::fake();
        $grant = $this->grant(hours: 1);

        $this->extend($grant, 4)->assertRedirect(route('tenants.support-access.show', $this->tenant));

        $this->assertTrue(now()->addHours(5)->equalTo($grant->fresh()->expires_at));
        $this->assertSame(1, SupportAccessGrant::count());

        $this->tenant->asCurrent(fn () => $this->assertSame(
            1,
            Activity::where('description', 'support_access.extended')->count(),
        ));
        Notification::assertSentTo($this->operator, SupportAccessExtended::class);
    }

    public function test_une_prolongation_ne_laisse_jamais_plus_que_la_duree_la_plus_longue_devant_soi(): void
    {
        $this->freezeSecond();
        $grant = $this->grant(hours: 20);

        // 20 + 12 = 32 heures : ramene a 24 heures a compter de maintenant.
        $this->extend($grant, 12)->assertSessionHasNoErrors();
        $this->assertTrue(now()->addHours(24)->equalTo($grant->fresh()->expires_at));

        // Deja au plafond : rien a ajouter.
        $this->extend($grant->fresh(), 4)->assertSessionHasErrors('duration');
        $this->assertTrue(now()->addHours(24)->equalTo($grant->fresh()->expires_at));
    }

    public function test_au_plafond_quelques_secondes_d_horloge_ne_font_pas_une_prolongation(): void
    {
        // Bogue trouve a l'essai : l'horloge avance entre deux gestes, le plafond recule d'autant, et
        // un acces deja au maximum etait « prolonge » de quelques secondes, avec courriel et ligne
        // d'historique.
        Notification::fake();
        $grant = $this->grant(hours: 24);

        $this->travel(5)->seconds();

        $this->extend($grant, 4)->assertSessionHasErrors('duration');
        Notification::assertNothingSent();
    }

    public function test_un_acces_ferme_ne_se_prolonge_pas(): void
    {
        $grant = $this->grant();
        $grant->update(['revoked_at' => now()]);

        $this->extend($grant, 4)->assertSessionHasErrors('duration');
    }

    public function test_la_prolongation_n_accepte_que_les_durees_proposees(): void
    {
        $this->extend($this->grant(), 7)->assertSessionHasErrors('duration');
    }

    public function test_seul_un_proprietaire_prolonge_et_jamais_l_acces_d_une_autre_organisation(): void
    {
        $grant = $this->grant();

        $this->extend($grant, 4, $this->operator)->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($stranger, 'Autre organisation');

        $this->actingAs($stranger)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('tenants.support-access.update', [$other, $grant]), ['duration' => 4])
            ->assertNotFound();
    }

    public function test_les_durees_proposees_viennent_des_reglages_de_la_console(): void
    {
        config(['convive.console.operators' => ['support@convive.test', 'fondateur@convive.test']]);
        $founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->actingAs($founder)
            ->put(route('console.security.support-durations.update'), ['durations' => '48, 2, 8'])
            ->assertRedirect(route('console.security'));

        $this->assertSame([2, 8, 48], app(SupportSettings::class)->durations);
        $this->assertSame([1, 4, 12, 24], ConsoleActionLog::where('type', 'support_durations_updated')->sole()->properties['old']['durations']);

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('durations', [2, 8, 48])->where('maxHours', 48));

        $this->open(['duration' => 48])->assertSessionHasNoErrors();
        $this->assertSame(1, SupportAccessGrant::count());
    }

    public function test_les_durees_reglees_sont_validees(): void
    {
        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        foreach (['', '0, 4', '4, quatre', '4, 500', '1,2,3,4,5,6,7,8,9'] as $invalid) {
            $this->actingAs($founder)
                ->put(route('console.security.support-durations.update'), ['durations' => $invalid])
                ->assertSessionHasErrors('durations');
        }

        $this->assertSame([1, 4, 12, 24], app(SupportSettings::class)->durations);
    }

    public function test_une_duree_retiree_des_reglages_n_est_plus_acceptee(): void
    {
        $settings = app(SupportSettings::class);
        $settings->durations = [1, 2];
        $settings->save();

        $this->open(['duration' => 24])->assertSessionHasErrors('duration');
    }
}
