<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tenants\ManageSupportAccess;
use App\Models\SupportAccessGrant;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\SupportAccessEnded;
use App\Notifications\Tenants\SupportAccessOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La vie d'un acces de support (README section 3) : la personne de l'equipe Convive est prevenue a
 * l'ouverture, peut le fermer elle-meme avec une note quand elle a termine, et les Proprietaires
 * sont prevenus quand il se termine, quelle qu'en soit la raison.
 */
class SupportAccessLifecycleTest extends TestCase
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

        $this->operator = User::factory()->withTwoFactor()->create(['email' => 'support@convive.test', 'support_available' => true]);
        config(['convive.console.operators' => ['support@convive.test']]);
    }

    private function grant(int $hours = 4): SupportAccessGrant
    {
        return SupportAccessGrant::create([
            'tenant_id' => $this->tenant->id,
            'operator_id' => $this->operator->id,
            'granted_by_id' => $this->owner->id,
            'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            'expires_at' => now()->addHours($hours),
        ]);
    }

    public function test_la_personne_de_l_equipe_convive_est_prevenue_a_l_ouverture(): void
    {
        Notification::fake();

        $this->actingAs($this->owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tenants.support-access.store', $this->tenant), [
                'operator_id' => $this->operator->id,
                'duration' => 4,
                'reason' => 'Les preuves du diner de gala n apparaissent pas.',
            ])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo(
            $this->operator,
            SupportAccessOpened::class,
            fn (SupportAccessOpened $mail) => $mail->grant->tenant_id === $this->tenant->id,
        );
    }

    public function test_la_personne_ferme_l_acces_quand_elle_a_termine_avec_une_note(): void
    {
        Notification::fake();
        $grant = $this->grant();

        $this->actingAs($this->operator)
            ->post(route('console.support-access.finish', $grant), ['note' => 'Les preuves etaient filtrees sur un autre evenement.'])
            ->assertRedirect(route('console.organisations.index'));

        $grant->refresh();

        $this->assertFalse($grant->isActive());
        $this->assertNotNull($grant->finished_at);
        $this->assertSame('Les preuves etaient filtrees sur un autre evenement.', $grant->closing_note);

        $this->actingAs($this->operator)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertNotFound();
    }

    public function test_la_note_de_fin_est_obligatoire(): void
    {
        $grant = $this->grant();

        $this->actingAs($this->operator)
            ->post(route('console.support-access.finish', $grant), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertTrue($grant->fresh()->isActive());
    }

    public function test_seule_la_personne_nommee_ferme_son_acces(): void
    {
        $grant = $this->grant();

        $colleague = User::factory()->withTwoFactor()->create(['email' => 'autre@convive.test']);
        config(['convive.console.operators' => ['support@convive.test', 'autre@convive.test']]);

        $this->actingAs($colleague)
            ->post(route('console.support-access.finish', $grant), ['note' => 'Une note assez longue.'])
            ->assertNotFound();

        $this->assertTrue($grant->fresh()->isActive());
    }

    public function test_les_proprietaires_sont_prevenus_quand_la_personne_a_termine(): void
    {
        Notification::fake();
        $grant = $this->grant();

        $this->actingAs($this->operator)
            ->post(route('console.support-access.finish', $grant), ['note' => 'Les preuves etaient filtrees sur un autre evenement.']);

        Notification::assertSentTo(
            $this->owner,
            SupportAccessEnded::class,
            fn (SupportAccessEnded $mail) => $mail->endReason === 'finished'
                && $mail->closingNote === 'Les preuves etaient filtrees sur un autre evenement.',
        );
    }

    public function test_les_autres_proprietaires_sont_prevenus_d_une_revocation(): void
    {
        Notification::fake();
        $grant = $this->grant();

        $second = User::factory()->withTwoFactor()->create();
        $this->tenant->addMember($second, $this->owner->tenantProfile($this->tenant));

        $this->actingAs($this->owner)
            ->delete(route('tenants.support-access.destroy', [$this->tenant, $grant]));

        Notification::assertSentTo($second, SupportAccessEnded::class, fn (SupportAccessEnded $mail) => $mail->endReason === 'revoked');
        // Celui qui revoque sait ce qu'il vient de faire.
        Notification::assertNotSentTo($this->owner, SupportAccessEnded::class);
    }

    public function test_les_proprietaires_sont_prevenus_une_seule_fois_a_l_echeance(): void
    {
        Notification::fake();
        $this->grant(hours: 1);

        app(ManageSupportAccess::class)->announceExpired();
        Notification::assertNothingSent();

        $this->travel(61)->minutes();

        app(ManageSupportAccess::class)->announceExpired();
        app(ManageSupportAccess::class)->announceExpired();

        Notification::assertSentToTimes($this->owner, SupportAccessEnded::class, 1);
        Notification::assertSentTo($this->owner, SupportAccessEnded::class, fn (SupportAccessEnded $mail) => $mail->endReason === 'expired');
    }

    public function test_l_historique_de_l_organisation_montre_la_note_de_fin(): void
    {
        Notification::fake();
        $grant = $this->grant();

        $this->actingAs($this->operator)
            ->post(route('console.support-access.finish', $grant), ['note' => 'Les preuves etaient filtrees sur un autre evenement.']);

        $this->actingAs($this->owner)
            ->get(route('tenants.support-access.show', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeAccess', null)
                ->where('pastAccesses.0.endReason', 'finished')
                ->where('pastAccesses.0.closingNote', 'Les preuves etaient filtrees sur un autre evenement.'),
            );
    }
}
