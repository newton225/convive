<?php

namespace Tests\Feature\Tickets;

use App\Actions\PaymentProofs\ValidatePaymentProof;
use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Emission du billet (README 2.8, ecran 7), etape 7 de « Ordre de construction ».
 */
class IssueTicketTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    public function test_emet_un_billet_pour_une_inscription_confirmee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $ticket = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return app(IssueTicket::class)->handle($registration);
        });

        $this->assertNotNull($ticket->id);
        $this->assertSame(1, $ticket->key_version);
        $this->assertNotEmpty($ticket->nonce);
    }

    public function test_genere_la_paire_de_cles_de_l_evenement_a_la_premiere_emission(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $event = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);
            app(IssueTicket::class)->handle($registration);

            return $event->fresh();
        });

        $this->assertNotNull($event->qr_public_key);
        $this->assertNotNull($event->qr_secret_key);
    }

    public function test_l_emission_est_idempotente(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        [$first, $second] = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id]);

            return [
                app(IssueTicket::class)->handle($registration),
                app(IssueTicket::class)->handle($registration->fresh()),
            ];
        });

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->nonce, $second->nonce);
        $this->assertSame(1, $tenant->asCurrent(fn () => Ticket::count()));
    }

    /**
     * README 2.8 : le billet est genere au meme moment que la table, a la validation de la
     * preuve.
     */
    public function test_la_validation_d_une_preuve_emet_le_billet(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $registration = $tenant->asCurrent(function () use ($owner) {
            $event = Event::factory()->open()->create();
            $registration = Registration::factory()->proofSubmitted()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);

            app(ValidatePaymentProof::class)->handle($proof, $owner);

            return $registration;
        });

        $this->assertNotNull($tenant->asCurrent(
            fn () => Ticket::where('registration_id', $registration->id)->first(),
        ));
    }
}
