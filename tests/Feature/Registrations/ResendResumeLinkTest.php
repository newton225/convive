<?php

namespace Tests\Feature\Registrations;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\ResumeLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * L'organisation renvoie a l'invite le lien de son dossier (reservation en cours ou preuve
 * rejetee), depuis la base d'inscrits, quand il l'a perdu (decision du 2026-10-16). Le lien neuf
 * part au telephone du dossier, jamais montre ni copie.
 */
class ResendResumeLinkTest extends TestCase
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
        $this->tenant->update(['subdomain' => 'convive-ci']);
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->published()->create());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function registration(RegistrationStatus $status, array $attributes = []): Registration
    {
        return $this->tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $this->event->id,
            'status' => $status,
            'held_until' => now()->addMinutes(8),
            'phone' => '+2250707000000',
            'resume_token_hash' => Registration::hashResumeToken('ancien-jeton'),
            ...$attributes,
        ]));
    }

    private function resend(Registration $registration, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)
            ->post(route('tenants.events.registrations.resume-link', [$this->tenant, $this->event, $registration]));
    }

    public function test_le_lien_d_un_dossier_dont_la_preuve_a_ete_rejetee_part_au_telephone_du_dossier(): void
    {
        Notification::fake();
        $registration = $this->registration(RegistrationStatus::ProofRejected);

        $this->resend($registration)
            ->assertRedirect(route('tenants.events.registrations.index', [$this->tenant, $this->event]));

        Notification::assertSentOnDemand(ResumeLink::class, fn ($notification, array $channels, $notifiable) => $notifiable->routes['whatsapp'] === '+2250707000000');
    }

    public function test_le_lien_d_une_reservation_en_cours_part_et_remplace_l_ancien(): void
    {
        Notification::fake();
        $registration = $this->registration(RegistrationStatus::Held);

        $this->resend($registration)->assertRedirect();

        Notification::assertSentOnDemand(ResumeLink::class);
        $this->assertNotSame(
            Registration::hashResumeToken('ancien-jeton'),
            $this->tenant->asCurrent(fn () => $registration->fresh())->resume_token_hash,
        );
    }

    public function test_une_preuve_en_attente_de_verification_ou_un_dossier_valide_n_a_aucun_lien_a_renvoyer(): void
    {
        Notification::fake();

        foreach ([RegistrationStatus::ProofSubmitted, RegistrationStatus::Confirmed, RegistrationStatus::Cancelled] as $status) {
            $registration = $this->registration($status, ['resume_token_hash' => Registration::hashResumeToken("jeton-{$status->value}")]);

            $this->resend($registration)->assertSessionHasErrors('resume_link');
        }

        Notification::assertNothingSent();
    }

    public function test_l_envoi_est_journalise_avec_son_auteur(): void
    {
        Notification::fake();
        $registration = $this->registration(RegistrationStatus::ProofRejected);

        $this->resend($registration);

        $activity = $this->tenant->asCurrent(fn () => Activity::where('description', 'registrations.resume_link_sent')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($this->owner->id, $activity->causer_id);
        $this->assertSame($registration->id, $activity->subject_id);
    }

    public function test_un_membre_avec_registrations_claims_renvoie_le_lien(): void
    {
        Notification::fake();
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::RegistrationsClaims]);

        $this->resend($this->registration(RegistrationStatus::ProofRejected), $member)->assertRedirect();

        Notification::assertSentOnDemand(ResumeLink::class);
    }

    public function test_un_membre_sans_registrations_claims_est_refuse(): void
    {
        Notification::fake();
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::RegistrationsView, TenantPermission::MessagesSend]);

        $this->resend($this->registration(RegistrationStatus::ProofRejected), $member)->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $registration = $this->registration(RegistrationStatus::ProofRejected);
        $stranger = User::factory()->withTwoFactor()->create();

        $this->resend($registration, $stranger)->assertNotFound();
    }

    public function test_une_inscription_d_un_autre_evenement_recoit_404(): void
    {
        $other = $this->tenant->asCurrent(fn () => Event::factory()->published()->create());
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $other->id,
            'status' => RegistrationStatus::ProofRejected,
        ]));

        $this->resend($registration)->assertNotFound();
    }
}
