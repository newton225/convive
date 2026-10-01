<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Http\Controllers\Tenants\SupportAccessController;
use App\Models\Profile;
use App\Models\SupportAccessRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\SupportAccessRequested;
use App\Notifications\Tenants\SupportAccessRequestTaken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La demande d'aide (README section 3) : quand personne de l'equipe Convive n'est visible, un
 * Proprietaire previent l'equipe ; celui qui prend la demande en charge devient visible, et
 * l'organisation lui ouvre ensuite l'acces. La demande n'ouvre rien par elle-meme.
 */
class SupportAccessRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $operator;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');

        // De l'equipe Convive, mais pas visible des organisations.
        $this->operator = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test', 'support_available' => false]);
        config(['convive.console.operators' => ['support@convive.test']]);
    }

    private function pendingRequest(?Tenant $tenant = null): SupportAccessRequest
    {
        return SupportAccessRequest::create([
            'tenant_id' => ($tenant ?? $this->tenant)->id,
            'requested_by_id' => $this->owner->id,
            'reason' => 'Les preuves du diner de gala n apparaissent pas.',
        ]);
    }

    public function test_un_proprietaire_previent_l_equipe_convive_quand_personne_n_est_visible(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('tenants.support-access.requests.store', $this->tenant), [
                'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            ])
            ->assertRedirect(route('tenants.support-access.show', $this->tenant));

        $this->assertTrue(SupportAccessRequest::where('tenant_id', $this->tenant->id)->pending()->exists());
        Notification::assertSentTo($this->operator, SupportAccessRequested::class);
    }

    public function test_la_demande_n_ouvre_aucun_acces(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->post(route('tenants.support-access.requests.store', $this->tenant), [
                'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            ]);

        $this->assertNull($this->operator->supportAccessTo($this->tenant));
    }

    public function test_une_demande_sans_motif_est_refusee(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.support-access.requests.store', $this->tenant), ['reason' => 'court'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, SupportAccessRequest::count());
    }

    public function test_une_seule_demande_en_attente_par_organisation(): void
    {
        Notification::fake();
        $this->pendingRequest();

        $this->actingAs($this->owner)
            ->post(route('tenants.support-access.requests.store', $this->tenant), [
                'reason' => 'Une seconde demande pour le meme probleme.',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(1, SupportAccessRequest::count());
        Notification::assertNothingSent();
    }

    public function test_seul_un_proprietaire_envoie_une_demande(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->tenant->addMember($member, $this->tenant->run(fn () => Profile::where('name', 'Lecture')->firstOrFail()));

        $this->actingAs($member)
            ->post(route('tenants.support-access.requests.store', $this->tenant), [
                'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            ])
            ->assertForbidden();

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.support-access.requests.store', $this->tenant), [
                'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            ])
            ->assertNotFound();
    }

    public function test_un_proprietaire_annule_sa_demande(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->owner)
            ->delete(route('tenants.support-access.requests.destroy', [$this->tenant, $request]))
            ->assertRedirect(route('tenants.support-access.show', $this->tenant));

        $this->assertFalse($request->fresh()->isPending());
    }

    public function test_la_demande_d_une_autre_organisation_est_introuvable(): void
    {
        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, 'Autre Association');
        $request = $this->pendingRequest($other);

        $this->actingAs($this->owner)
            ->delete(route('tenants.support-access.requests.destroy', [$this->tenant, $request]))
            ->assertNotFound();

        $this->assertTrue($request->fresh()->isPending());
    }

    public function test_prendre_la_demande_en_charge_rend_la_personne_visible_et_previent_les_proprietaires(): void
    {
        Notification::fake();
        $request = $this->pendingRequest();

        $this->actingAs($this->operator)
            ->post(route('console.support-requests.take', $request))
            ->assertRedirect(route('console.organisations.index'));

        $this->assertSame($this->operator->id, $request->fresh()->taken_by_id);
        $this->assertTrue(SupportAccessController::operators($this->tenant)->contains('id', $this->operator->id));
        $this->assertFalse($this->operator->fresh()->support_available);
        Notification::assertSentTo($this->owner, SupportAccessRequestTaken::class);
        // Toujours aucun acces : c'est l'organisation qui l'ouvre.
        $this->assertNull($this->operator->supportAccessTo($this->tenant));
    }

    public function test_la_personne_n_est_visible_que_de_l_organisation_qui_a_demande(): void
    {
        Notification::fake();
        $otherOwner = User::factory()->withTwoFactor()->create();
        $other = app(CreateTenant::class)->handle($otherOwner, 'Autre Association');

        $this->actingAs($this->operator)->post(route('console.support-requests.take', $this->pendingRequest()));

        $this->assertTrue(SupportAccessController::operators($other)->isEmpty());

        // Et l'autre organisation ne peut pas lui ouvrir d'acces.
        $this->actingAs($otherOwner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tenants.support-access.store', $other), [
                'operator_id' => $this->operator->id,
                'duration' => 4,
                'reason' => 'Tentative depuis une autre organisation.',
            ])
            ->assertSessionHasErrors('operator_id');
    }

    public function test_la_personne_n_est_plus_visible_une_fois_la_demande_annulee(): void
    {
        Notification::fake();
        $request = $this->pendingRequest();

        $this->actingAs($this->operator)->post(route('console.support-requests.take', $request));
        $this->actingAs($this->owner)->delete(route('tenants.support-access.requests.destroy', [$this->tenant, $request]));

        $this->assertTrue(SupportAccessController::operators($this->tenant)->isEmpty());
    }

    public function test_un_compte_hors_de_l_equipe_convive_ne_prend_pas_une_demande_en_charge(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->owner)
            ->post(route('console.support-requests.take', $request))
            ->assertNotFound();

        $this->assertNull($request->fresh()->taken_by_id);
    }

    public function test_ouvrir_l_acces_ferme_la_demande(): void
    {
        Notification::fake();
        $request = $this->pendingRequest();
        $request->update(['taken_by_id' => $this->operator->id, 'taken_at' => now()]);

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tenants.support-access.store', $this->tenant), [
                'operator_id' => $this->operator->id,
                'duration' => 4,
                'reason' => $request->reason,
            ]);

        $this->assertFalse($request->fresh()->isPending());
    }

    public function test_l_ecran_montre_la_demande_en_attente_et_la_console_la_liste(): void
    {
        $request = $this->pendingRequest();

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pendingRequest.id', $request->id)
                ->where('pendingRequest.takenBy', null),
            );

        $this->actingAs($this->operator)
            ->get(route('console.organisations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('supportRequests', 1)
                ->where('supportRequests.0.organisation', 'Association Convive'),
            );
    }
}
