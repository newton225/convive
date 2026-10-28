<?php

namespace Tests\Feature\Registrations;

use App\Actions\Tenants\CreateTenant;
use App\Enums\RegistrationEventType;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\RegistrationJournal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Le suivi des telechargements de billets pour tout un evenement (decision du 2026-10-28) : une colonne, un
 * filtre et un resume sur la liste des inscrits, pour savoir qui n'a jamais recupere son billet.
 */
class TicketDownloadFollowUpTest extends TestCase
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

    /**
     * @return array{downloaded: Registration, twice: Registration, never: Registration, unconfirmed: Registration}
     */
    private function guests(): array
    {
        return $this->tenant->asCurrent(function () {
            $downloaded = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Aya Telecharge']);
            $twice = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Bamba Deux Fois']);
            $never = Registration::factory()->confirmed()->create(['event_id' => $this->event->id, 'name' => 'Cece Jamais']);
            $unconfirmed = Registration::factory()->held()->create(['event_id' => $this->event->id, 'name' => 'Dede Reserve']);

            RegistrationJournal::guest(RegistrationEventType::TicketDownloaded, $downloaded, ['scope' => 'group', 'count' => 1]);
            RegistrationJournal::guest(RegistrationEventType::TicketDownloaded, $twice, ['scope' => 'group', 'count' => 1]);
            RegistrationJournal::guest(RegistrationEventType::TicketDownloaded, $twice, ['scope' => 'single', 'ticket_id' => 1]);

            // Un fait sans dossier (liste d'attente) : il ne doit jamais fausser le filtre « jamais telecharge ».
            $entry = WaitlistEntry::factory()->create(['event_id' => $this->event->id]);
            RegistrationJournal::system(RegistrationEventType::WaitlistInvited, $entry);

            return compact('downloaded', 'twice', 'never', 'unconfirmed');
        });
    }

    private function list(array $filter = []): TestResponse
    {
        return $this->actingAs($this->owner)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event, 'filter' => $filter]));
    }

    public function test_la_liste_donne_pour_chaque_inscrit_le_nombre_et_la_date_du_dernier_telechargement(): void
    {
        $guests = $this->guests();

        $this->list(['search' => 'Bamba'])->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $guests['twice']->id)
            ->where('rows.0.ticketDownloads', 2)
            ->whereNot('rows.0.ticketDownloadedAt', null),
        );

        $this->list(['search' => 'Cece'])->assertInertia(fn ($page) => $page
            ->where('rows.0.ticketDownloads', 0)
            ->where('rows.0.ticketDownloadedAt', null),
        );
    }

    public function test_le_resume_compte_les_inscrits_confirmes_qui_ont_telecharge_et_ceux_qui_ne_l_ont_jamais_fait(): void
    {
        $this->guests();

        $this->list()->assertInertia(fn ($page) => $page
            ->where('ticketSummary.confirmed', 3)
            ->where('ticketSummary.downloaded', 2)
            ->where('ticketSummary.never', 1),
        );
    }

    public function test_le_filtre_telecharge_ne_garde_que_ceux_qui_l_ont_fait(): void
    {
        $guests = $this->guests();

        $this->list(['ticket' => 'downloaded'])->assertInertia(fn ($page) => $page
            ->has('rows', 2)
            ->where('meta.total', 2),
        );

        $ids = collect($this->list(['ticket' => 'downloaded'])->viewData('page')['props']['rows'])->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$guests['downloaded']->id, $guests['twice']->id], $ids);
    }

    public function test_le_filtre_jamais_telecharge_ne_garde_que_les_confirmes_sans_telechargement(): void
    {
        $guests = $this->guests();

        $response = $this->list(['ticket' => 'never_downloaded']);

        $response->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $guests['never']->id),
        );
    }

    public function test_un_dossier_non_confirme_n_a_pas_de_billet_et_n_entre_pas_dans_le_suivi(): void
    {
        $guests = $this->guests();

        $this->list(['search' => 'Dede'])->assertInertia(fn ($page) => $page
            ->where('rows.0.id', $guests['unconfirmed']->id)
            ->where('rows.0.ticketDownloads', 0),
        );

        $this->list(['ticket' => 'never_downloaded'])->assertInertia(fn ($page) => $page
            ->where('rows.0.id', $guests['never']->id)
            ->has('rows', 1),
        );
    }

    public function test_les_telechargements_d_un_autre_evenement_ne_comptent_pas(): void
    {
        $guests = $this->guests();

        $this->tenant->asCurrent(function () {
            $other = Event::factory()->open()->create();
            $foreign = Registration::factory()->confirmed()->create(['event_id' => $other->id]);
            RegistrationJournal::guest(RegistrationEventType::TicketDownloaded, $foreign, ['scope' => 'group', 'count' => 1]);
        });

        $this->list()->assertInertia(fn ($page) => $page
            ->where('ticketSummary.confirmed', 3)
            ->where('ticketSummary.downloaded', 2),
        );

        $this->assertNotNull($guests['never']);
    }

    public function test_un_evenement_sans_inscrit_confirme_a_un_resume_vide(): void
    {
        $this->list()->assertInertia(fn ($page) => $page
            ->where('ticketSummary.confirmed', 0)
            ->where('ticketSummary.never', 0),
        );
    }

    public function test_un_tiers_n_atteint_pas_le_suivi_d_une_autre_organisation(): void
    {
        $this->guests();

        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre Organisation');

        $this->actingAs($stranger)
            ->get(route('tenants.events.registrations.index', [$this->tenant, $this->event, 'filter' => ['ticket' => 'downloaded']]))
            ->assertNotFound();
    }
}
