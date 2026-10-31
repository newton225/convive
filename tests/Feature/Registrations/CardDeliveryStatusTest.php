<?php

namespace Tests\Feature\Registrations;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Registrations\InvitationCard;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'etat d'envoi de la carte sur la liste des inscrits (decision du 2026-10-31) : les canaux, et l'echec
 * du service d'envoi, que la date `card_sent_at` (mise en file) ne montre pas.
 */
class CardDeliveryStatusTest extends TestCase
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
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function row(Registration $registration): array
    {
        $rows = $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event]))
            ->viewData('page')['props']['rows'];

        return collect($rows)->firstWhere('id', $registration->id);
    }

    public function test_la_carte_part_par_whatsapp_seulement_sans_courriel(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $this->event->id, 'email' => null,
        ]));

        $this->assertSame(['whatsapp'], $this->row($registration)['cardChannels']);
    }

    public function test_la_carte_part_aussi_par_courriel_quand_l_invite_en_a_donne_un(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $this->event->id, 'email' => 'aya@example.com',
        ]));

        $this->assertSame(['whatsapp', 'mail'], $this->row($registration)['cardChannels']);
    }

    public function test_un_echec_du_service_d_envoi_se_marque_sur_le_dossier(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $this->event->id, 'card_sent_at' => now(),
        ]));

        $this->tenant->asCurrent(fn () => (new InvitationCard($registration, 'https://example.test/lien'))->failed(new Exception('refuse')));

        $row = $this->row($registration);

        $this->assertNotNull($row['cardFailedAt']);
        $this->assertNotNull($row['cardSentAt']);
    }

    public function test_un_dossier_dont_l_envoi_n_a_pas_echoue_n_a_pas_de_date_d_echec(): void
    {
        $registration = $this->tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $this->event->id, 'card_sent_at' => now(),
        ]));

        $this->assertNull($this->row($registration)['cardFailedAt']);
    }
}
