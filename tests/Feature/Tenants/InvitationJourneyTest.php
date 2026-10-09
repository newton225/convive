<?php

namespace Tests\Feature\Tenants;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Profile;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Notifications\TenantAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le parcours d'une personne invitee a rejoindre une equipe (TODO du 2026-10-07, points 8 a 10) :
 * le lien mene a l'inscription ou a la connexion, l'invitation suit tout le parcours, l'adresse doit
 * etre celle de l'invitation, l'acceptation est explicite, et une adresse differente ne mene jamais
 * a une page vide.
 */
class InvitationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private TenantInvitation $invitation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = Tenant::factory()->create(['name' => 'Association Convive']);
        $this->joinAsOwner($this->tenant, $this->owner);

        $this->invitation = TenantInvitation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'profile_id' => $this->profileOf($this->tenant, 'Lecture')->id,
            'profile_name' => 'Lecture',
            'email' => 'aya@example.com',
            'invited_by' => $this->owner->id,
        ]);
    }

    private function profileOf(Tenant $tenant, string $name): Profile
    {
        return $tenant->run(fn () => Profile::where('name', $name)->firstOrFail());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aya Kouassi',
            'email' => 'aya@example.com',
            'phone' => '+225 07 07 12 34 56',
            'password' => 'Convive-2026!',
            'password_confirmation' => 'Convive-2026!',
            'terms' => 'on',
            'invitation' => $this->invitation->code,
        ], $overrides);
    }

    public function test_l_inscription_depuis_le_lien_presente_l_organisation_et_l_adresse_invitee(): void
    {
        $this->get(route('register', ['invitation' => $this->invitation->code]))
            ->assertOk()
            ->assertSessionHas('tenant_invitation', $this->invitation->code)
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/register')
                ->where('tenantInvitation.tenantName', 'Association Convive')
                ->where('tenantInvitation.email', 'aya@example.com'));
    }

    public function test_l_inscription_exige_l_adresse_invitee(): void
    {
        $this->post(route('register.store'), $this->registration(['email' => 'autre@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'autre@example.com']);
    }

    public function test_l_inscription_par_invitation_ne_cree_pas_d_organisation_personnelle(): void
    {
        $this->post(route('register.store'), $this->registration())
            ->assertRedirect(route('invitations.index'));

        $user = User::where('email', 'aya@example.com')->firstOrFail();

        $this->assertCount(0, $user->tenants()->get());
        $this->assertNull($user->personalTenant());
    }

    public function test_l_inscription_par_invitation_vaut_verification_de_l_adresse(): void
    {
        $this->post(route('register.store'), $this->registration());

        $this->assertTrue(User::where('email', 'aya@example.com')->firstOrFail()->hasVerifiedEmail());
    }

    public function test_l_inscription_n_accepte_pas_l_invitation_a_la_place_de_la_personne(): void
    {
        $this->post(route('register.store'), $this->registration());

        $user = User::where('email', 'aya@example.com')->firstOrFail();

        $this->assertFalse($user->belongsToTenant($this->tenant));
        $this->assertNull($this->invitation->fresh()->accepted_at);

        $this->actingAs($user)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations')
                ->where('invitations.0.code', $this->invitation->code)
                ->where('invitations.0.tenantName', 'Association Convive')
                ->where('hasOrganisation', false));
    }

    public function test_l_acceptation_explicite_ajoute_la_personne_a_l_organisation(): void
    {
        $this->post(route('register.store'), $this->registration());
        $user = User::where('email', 'aya@example.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('invitations.accept', $this->invitation))
            ->assertRedirect(route('dashboard', $this->tenant));

        $this->assertTrue($user->fresh()->belongsToTenant($this->tenant));
        $this->assertSame('Lecture', $user->fresh()->tenantProfile($this->tenant)?->name);
    }

    public function test_un_compte_existant_garde_l_invitation_a_travers_la_connexion(): void
    {
        $user = User::factory()->create(['email' => 'aya@example.com']);

        $this->get(route('login', ['invitation' => $this->invitation->code]))
            ->assertOk()
            ->assertSessionHas('tenant_invitation', $this->invitation->code);

        $this->post(route('login.store'), ['email' => 'aya@example.com', 'password' => 'password'])
            ->assertRedirect(route('invitations.index'));

        $this->actingAs($user)
            ->get(route('invitations.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitations.0.code', $this->invitation->code)
                ->where('mismatch', null));
    }

    public function test_un_compte_a_une_autre_adresse_voit_un_message_et_jamais_l_invitation_rattachee(): void
    {
        $other = User::factory()->create(['email' => 'koffi@example.com']);

        $this->get(route('login', ['invitation' => $this->invitation->code]));

        $this->post(route('login.store'), ['email' => 'koffi@example.com', 'password' => 'password'])
            ->assertRedirect(route('invitations.index'));

        $this->actingAs($other)
            ->get(route('invitations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations')
                ->where('invitations', [])
                ->where('mismatch.tenantName', 'Association Convive')
                ->where('mismatch.invitedEmail', 'aya@example.com')
                ->where('mismatch.accountEmail', 'koffi@example.com'));

        $this->actingAs($other)
            ->post(route('invitations.accept', $this->invitation))
            ->assertSessionHasErrors('invitation');

        $this->assertFalse($other->fresh()->belongsToTenant($this->tenant));
    }

    public function test_le_message_d_adresse_differente_reste_jusqu_a_ce_que_la_personne_choisisse(): void
    {
        $other = User::factory()->create(['email' => 'koffi@example.com']);

        $this->actingAs($other)
            ->withSession(['tenant_invitation' => $this->invitation->code])
            ->get(route('invitations.index'))
            ->assertInertia(fn (Assert $page) => $page->where('mismatch.invitedEmail', 'aya@example.com'));

        $this->actingAs($other)
            ->delete(route('invitations.forget'))
            ->assertRedirect()
            ->assertSessionMissing('tenant_invitation');
    }

    public function test_un_compte_sans_organisation_arrive_sur_l_accueil_des_invitations(): void
    {
        $user = User::factory()->withoutOrganisation()->create(['email' => 'aya@example.com']);

        // Connexion, puis retour par le logo : jamais une erreur ni une page vide.
        $this->post(route('login.store'), ['email' => 'aya@example.com', 'password' => 'password'])
            ->assertRedirect(route('invitations.index'));

        $this->actingAs($user)
            ->get(route('home'))
            ->assertRedirect(route('invitations.index'));

        $this->actingAs($user)
            ->get(route('invitations.index'))
            ->assertOk();
    }

    public function test_un_membre_qui_a_deja_une_organisation_revient_a_son_tableau_de_bord_par_le_logo(): void
    {
        $this->owner->forceFill(['current_tenant_id' => $this->tenant->id])->save();
        $this->owner->unsetRelation('currentTenant');

        $this->actingAs($this->owner)
            ->get(route('home'))
            ->assertRedirect(route('dashboard', $this->tenant));
    }

    public function test_tous_les_proprietaires_sont_prevenus_de_l_acceptation(): void
    {
        Notification::fake();

        $secondOwner = User::factory()->withTwoFactor()->create();
        $this->joinAsOwner($this->tenant, $secondOwner);
        $reader = User::factory()->withTwoFactor()->create();
        $this->joinWithProfile($this->tenant, $reader, 'Lecture');

        $user = User::factory()->create(['email' => 'aya@example.com', 'name' => 'Aya Kouassi']);

        $this->actingAs($user)->post(route('invitations.accept', $this->invitation));

        $isAcceptance = fn (TenantAlert $alert) => $alert->type === NotificationType::TeamInvitationAccepted
            && $alert->params['name'] === 'Aya Kouassi'
            && $alert->url === route('tenants.edit', $this->tenant, absolute: false);

        Notification::assertSentTo([$this->owner, $secondOwner], TenantAlert::class, $isAcceptance);
        Notification::assertNotSentTo([$reader, $user], TenantAlert::class, $isAcceptance);
    }

    public function test_l_alerte_d_acceptation_part_dans_l_application_et_par_courriel_par_defaut(): void
    {
        $this->assertSame(NotificationChannel::Both, $this->owner->notificationChannelFor(NotificationType::TeamInvitationAccepted));
    }
}
