<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\PaymentChannel;
use App\Enums\RefundStatus;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/**
 * L'ecran Tresorerie : les totaux de toute l'organisation, par evenement et par compte de versement,
 * et ce qui reste a rembourser (decision du 2026-10-16). Les montants reprennent
 * `Event::paymentTotals()` : aucun total ne doit diverger de celui du rapport de l'evenement.
 */
class TreasuryTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function event(array $event = []): Event
    {
        return $this->tenant->asCurrent(fn () => Event::factory()->open()->create($event));
    }

    private function confirmed(Event $event, int $amount, ?PaymentAccount $account = null, array $attributes = []): Registration
    {
        return $this->tenant->asCurrent(function () use ($event, $amount, $account, $attributes) {
            $registration = Registration::factory()->confirmed()->create([
                'event_id' => $event->id,
                'amount_due' => $amount,
                ...$attributes,
            ]);

            if ($account !== null) {
                PaymentProof::factory()->create([
                    'registration_id' => $registration->id,
                    'payment_account_id' => $account->id,
                    'channel' => $account->channel,
                ]);
            }

            return $registration;
        });
    }

    private function account(string $label, PaymentChannel $channel): PaymentAccount
    {
        return $this->tenant->asCurrent(fn () => PaymentAccount::factory()->create(['label' => $label, 'channel' => $channel]));
    }

    private function show(array $query = [], ?User $as = null)
    {
        return $this->actingAs($as ?? $this->owner)->get(route('tenants.treasury', [$this->tenant, ...$query]));
    }

    public function test_les_totaux_additionnent_tous_les_evenements_de_l_organisation(): void
    {
        $first = $this->event();
        $second = $this->event();
        $this->confirmed($first, 10000);
        $this->confirmed($first, 5000);
        $this->confirmed($second, 20000);
        $this->tenant->asCurrent(fn () => Registration::factory()->refundDue()->create([
            'event_id' => $second->id,
            'amount_due' => 3000,
        ]));

        $this->show()
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('tenants/treasury')
                ->where('totals.collected', 38000)
                ->where('totals.refunded', 0)
                ->where('totals.due', 3000)
                ->where('totals.net', 38000)
                ->has('events', 2));
    }

    public function test_chaque_evenement_porte_les_memes_totaux_que_son_rapport(): void
    {
        $event = $this->event();
        $this->confirmed($event, 12000);
        $this->tenant->asCurrent(fn () => Registration::factory()->refundDue()->create(['event_id' => $event->id, 'amount_due' => 4000]));

        $expected = $this->tenant->asCurrent(fn () => $event->fresh()->paymentTotals());

        $this->show()->assertInertia(fn ($page) => $page
            ->where('events.0.id', $event->id)
            ->where('events.0.collected', $expected['collected'])
            ->where('events.0.due', $expected['due'])
            ->where('events.0.net', $expected['net']));
    }

    public function test_le_filtre_de_periode_garde_les_evenements_dont_la_date_est_dans_la_periode(): void
    {
        $october = $this->event(['starts_at' => '2026-10-10 18:00:00']);
        $december = $this->event(['starts_at' => '2026-12-18 18:00:00']);
        $this->confirmed($october, 7000);
        $this->confirmed($december, 9000);

        $this->show(['from' => '2026-12-01', 'to' => '2026-12-31'])->assertInertia(fn ($page) => $page
            ->has('events', 1)
            ->where('events.0.id', $december->id)
            ->where('totals.collected', 9000));
    }

    public function test_une_periode_a_l_envers_est_refusee(): void
    {
        $this->show(['from' => '2026-12-31', 'to' => '2026-12-01'])->assertSessionHasErrors('to');
    }

    public function test_la_repartition_par_compte_de_versement(): void
    {
        $event = $this->event();
        $wave = $this->account('Wave principal', PaymentChannel::Wave);
        $orange = $this->account('Orange Money', PaymentChannel::OrangeMoney);
        $this->confirmed($event, 10000, $wave);
        $this->confirmed($event, 5000, $wave);
        $this->confirmed($event, 8000, $orange);

        $this->show()->assertInertia(fn ($page) => $page
            ->has('accounts', 2)
            ->where('accounts.0.label', 'Wave principal')
            ->where('accounts.0.collected', 15000)
            ->where('accounts.1.label', 'Orange Money')
            ->where('accounts.1.collected', 8000));
    }

    public function test_la_liste_des_remboursements_a_faire_couvre_tous_les_evenements(): void
    {
        $first = $this->event();
        $second = $this->event();
        $this->tenant->asCurrent(function () use ($first, $second) {
            Registration::factory()->refundDue()->create(['event_id' => $first->id, 'amount_due' => 4000, 'name' => 'Aya Kouame']);
            Registration::factory()->refundDue()->create(['event_id' => $second->id, 'amount_due' => 6000, 'name' => 'Koffi Brou']);
            Registration::factory()->cancelled()->create(['event_id' => $second->id, 'amount_due' => 2000, 'refund_status' => RefundStatus::Refunded, 'refund_fee' => 0, 'name' => 'Deja Rendu']);
        });

        $this->show()->assertInertia(fn ($page) => $page
            ->has('refundsDue', 2)
            ->where('refundsDue.0.amount', fn ($amount) => in_array($amount, [4000, 6000], true))
            ->has('refundsDue.0.eventName')
            ->has('refundsDue.0.registrationsUrl'));
    }

    public function test_un_membre_avec_reports_view_voit_l_ecran(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::ReportsView]);

        $this->show(as: $member)->assertOk();
    }

    public function test_un_membre_sans_reports_view_est_refuse(): void
    {
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::EventsView]);

        $this->show(as: $member)->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();

        $this->show(as: $stranger)->assertNotFound();
    }

    public function test_un_evenement_a_la_corbeille_n_entre_pas_dans_les_totaux(): void
    {
        $event = $this->event();
        $this->confirmed($event, 10000);
        $this->tenant->asCurrent(fn () => $event->delete());

        $this->show()->assertInertia(fn ($page) => $page
            ->has('events', 0)
            ->where('totals.collected', 0));
    }

    private function exportUrl(array $query = []): string
    {
        return route('tenants.treasury.export.pdf', [$this->tenant, ...$query]);
    }

    public function test_l_export_pdf_rend_la_synthese_avec_les_memes_totaux_que_l_ecran(): void
    {
        Pdf::fake();
        $event = $this->event();
        $this->confirmed($event, 12000);

        $this->actingAs($this->owner)->get($this->exportUrl())->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.treasury'
            && $pdf->viewData['overview']['totals']['collected'] === 12000
            && count($pdf->viewData['overview']['events']) === 1);
    }

    public function test_l_export_pdf_respecte_la_periode_choisie(): void
    {
        Pdf::fake();
        $october = $this->event(['starts_at' => '2026-10-10 18:00:00']);
        $december = $this->event(['starts_at' => '2026-12-18 18:00:00']);
        $this->confirmed($october, 7000);
        $this->confirmed($december, 9000);

        $this->actingAs($this->owner)->get($this->exportUrl(['from' => '2026-12-01', 'to' => '2026-12-31']))->assertOk();

        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewData['overview']['totals']['collected'] === 9000);
    }

    public function test_l_export_pdf_exige_la_permission_d_export_des_rapports(): void
    {
        Pdf::fake();
        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($this->tenant, $member, [TenantPermission::ReportsView]);

        $this->actingAs($member)->get($this->exportUrl())->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_l_export_pdf(): void
    {
        Pdf::fake();
        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)->get($this->exportUrl())->assertNotFound();
    }

    public function test_l_export_pdf_est_journalise_avec_son_auteur(): void
    {
        Pdf::fake();

        $this->actingAs($this->owner)->get($this->exportUrl(['from' => '2026-12-01', 'to' => '2026-12-31']))->assertOk();

        $activity = $this->tenant->asCurrent(fn () => Activity::where('description', 'treasury.exported')->latest('id')->first());

        $this->assertNotNull($activity);
        $this->assertSame($this->owner->id, $activity->causer_id);
        $this->assertSame('2026-12-01', $activity->properties['from']);
    }

    public function test_l_export_pdf_est_limite_en_debit(): void
    {
        Pdf::fake();

        for ($i = 0; $i < AppServiceProvider::ExportsPerHour; $i++) {
            $this->actingAs($this->owner)->get($this->exportUrl())->assertOk();
        }

        $this->actingAs($this->owner)->get($this->exportUrl())->assertStatus(429);
    }
}
