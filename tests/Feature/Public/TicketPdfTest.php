<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/**
 * Billets en PDF (README 2.8) : l'invite, ou un accompagnateur, garde ses billets hors ligne pour
 * les presenter a l'entree meme sans connexion stable.
 */
class TicketPdfTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
        ])->save();
        $tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant = $tenant->fresh();
    }

    /**
     * @return array{registration: Registration, tickets: array<int, Ticket>}
     */
    private function confirmedGroup(): array
    {
        return $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create();
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'name' => 'Aya Kouassi',
                'party_size' => 3,
            ]);
            $unit = Unit::query()->value('id');
            $registration->companions()->create(['name' => 'Kofi Kouassi', 'unit_id' => $unit, 'position' => 0]);
            $registration->companions()->create(['name' => 'Marie Kouassi', 'unit_id' => $unit, 'position' => 1]);
            app(IssueTicket::class)->handle($registration);

            return [
                'registration' => $registration,
                'tickets' => Ticket::where('registration_id', $registration->id)->orderBy('holder_position')->get()->all(),
            ];
        });
    }

    /**
     * @return array<int, string>
     */
    private function holderNames(PdfBuilder $pdf): array
    {
        return array_column($pdf->viewData['tickets'], 'name');
    }

    public function test_le_billet_d_un_accompagnateur_se_telecharge_seul_en_pdf(): void
    {
        Pdf::fake();
        ['tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->pdfUrl());

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $this->holderNames($pdf) === ['Kofi Kouassi']);
    }

    public function test_l_invite_telecharge_les_billets_de_tout_son_groupe_en_pdf(): void
    {
        Pdf::fake();
        ['registration' => $registration] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $registration->fresh()->ticketsPdfUrl());

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $this->holderNames($pdf) === ['Aya Kouassi', 'Kofi Kouassi', 'Marie Kouassi']);
    }

    public function test_le_billet_de_l_invite_principal_liste_ses_accompagnateurs(): void
    {
        Pdf::fake();
        ['registration' => $registration] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $registration->fresh()->ticketsPdfUrl());

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) {
            [$holder, $companion] = $pdf->viewData['tickets'];

            return array_column($holder['companions'], 'name') === ['Kofi Kouassi', 'Marie Kouassi']
                && $holder['host'] === null
                && $companion['companions'] === [];
        });
    }

    public function test_la_liste_des_accompagnateurs_suit_le_gabarit_du_billet(): void
    {
        Pdf::fake();
        ['registration' => $registration] = $this->confirmedGroup();
        $this->tenant->brandingOrCreate()->update(['ticket_element_companions' => false]);

        $url = $this->tenant->asCurrent(fn () => $registration->fresh()->ticketsPdfUrl());

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['tickets'][0]['companions'] === []);
    }

    public function test_le_billet_d_un_accompagnateur_presente_la_personne_qui_l_invite(): void
    {
        Pdf::fake();
        ['registration' => $registration, 'tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->pdfUrl());

        $this->get($url)->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['tickets'][0]['host']['name'] === 'Aya Kouassi'
            && $pdf->viewData['tickets'][0]['host']['reference'] === $registration->fresh()->reference
            && $pdf->viewData['tickets'][0]['host']['unit'] !== '');
    }

    public function test_un_lien_pdf_altere_repond_404(): void
    {
        Pdf::fake();
        ['registration' => $registration, 'tickets' => $tickets] = $this->confirmedGroup();

        $ticketUrl = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->pdfUrl());
        $groupUrl = $this->tenant->asCurrent(fn () => $registration->fresh()->ticketsPdfUrl());

        $this->get(preg_replace('/signature=[^&]+/', 'signature=invalide', (string) $ticketUrl))->assertNotFound();
        $this->get(preg_replace('/signature=[^&]+/', 'signature=invalide', (string) $groupUrl))->assertNotFound();
    }

    public function test_une_inscription_annulee_n_a_plus_de_billets_a_telecharger(): void
    {
        Pdf::fake();
        ['registration' => $registration, 'tickets' => $tickets] = $this->confirmedGroup();

        $ticketUrl = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->pdfUrl());
        $groupUrl = $this->tenant->asCurrent(fn () => $registration->fresh()->ticketsPdfUrl());
        $this->tenant->asCurrent(fn () => $registration->update(['status' => 'cancelled']));

        $this->get((string) $ticketUrl)->assertNotFound();
        $this->get((string) $groupUrl)->assertNotFound();
    }

    public function test_la_page_du_billet_propose_le_telechargement(): void
    {
        ['tickets' => $tickets] = $this->confirmedGroup();

        $url = $this->tenant->asCurrent(fn () => $tickets[1]->fresh()->shareUrl());

        $this->get((string) $url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('ticket.pdfUrl'));
    }
}
