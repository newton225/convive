<?php

namespace Tests\Feature\Console;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Le nettoyage unique d'une organisation (decision du 2026-11-01) : tous ses evenements sauf ceux a conserver,
 * avec inscriptions, preuves et fichiers. Simulation par defaut, `--keep` obligatoire.
 */
class EraseEventsCommandTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Event $old;

    private Event $latest;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->tenant->asCurrent(function () {
            $this->old = Event::factory()->published()->create(['name' => 'Evenement d essai']);
            $this->latest = Event::factory()->published()->create(['name' => 'Evenement a garder']);

            foreach ([$this->old, $this->latest] as $event) {
                $registration = Registration::factory()->confirmed()->create(['event_id' => $event->id, 'name' => 'Aya Kouame']);
                $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);
                $proof->addMedia(UploadedFile::fake()->image('capture.png'))->toMediaCollection(PaymentProof::ReceiptCollection);

                WaitlistEntry::factory()->create(['event_id' => $event->id]);
            }

            ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'event_id' => $this->old->id]);
        });
    }

    /**
     * @return array<string, int>
     */
    private function counts(Event $event): array
    {
        return $this->tenant->asCurrent(fn () => [
            'event' => Event::withTrashed()->whereKey($event->id)->count(),
            'registrations' => Registration::where('event_id', $event->id)->count(),
            'proofs' => PaymentProof::whereIn('registration_id', Registration::where('event_id', $event->id)->select('id'))->count(),
            'waitlist' => WaitlistEntry::where('event_id', $event->id)->count(),
        ]);
    }

    public function test_sans_keep_la_commande_refuse_pour_ne_jamais_tout_effacer(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--force' => true, '--no-interaction' => true])
            ->assertFailed();

        $this->assertSame(1, $this->counts($this->old)['event']);
    }

    public function test_un_evenement_a_conserver_inconnu_est_refuse(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [99999], '--force' => true, '--no-interaction' => true])
            ->assertFailed();

        $this->assertSame(1, $this->counts($this->old)['event']);
    }

    public function test_une_organisation_inconnue_est_refusee(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => 'inconnue', '--keep' => [$this->latest->id]])
            ->assertFailed();
    }

    public function test_sans_force_la_commande_simule_et_n_efface_rien(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id]])
            ->expectsOutputToContain('Simulation')
            ->assertSuccessful();

        $this->assertSame(['event' => 1, 'registrations' => 1, 'proofs' => 1, 'waitlist' => 1], $this->counts($this->old));
        $this->assertCount(2, Storage::disk('payment_proofs')->allFiles());
    }

    public function test_avec_force_les_autres_evenements_partent_avec_tout_ce_qui_s_y_rattache(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame(['event' => 0, 'registrations' => 0, 'proofs' => 0, 'waitlist' => 0], $this->counts($this->old));
    }

    public function test_l_evenement_conserve_et_ses_donnees_restent_intacts(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame(['event' => 1, 'registrations' => 1, 'proofs' => 1, 'waitlist' => 1], $this->counts($this->latest));
    }

    public function test_la_capture_de_preuve_de_l_evenement_efface_quitte_le_disque(): void
    {
        $this->assertCount(2, Storage::disk('payment_proofs')->allFiles());

        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        // Il ne reste que la capture de l'evenement conserve.
        $this->assertCount(1, Storage::disk('payment_proofs')->allFiles());
    }

    public function test_l_annonce_sur_la_vitrine_est_retiree(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame(0, ShowcaseEvent::where('event_id', $this->old->id)->count());
    }

    public function test_le_journal_garde_le_nom_et_les_effectifs_sans_donnee_personnelle(): void
    {
        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $entry = $this->tenant->asCurrent(fn () => Activity::where('description', 'event.erased')->first());

        $this->assertNotNull($entry);
        $this->assertSame('Evenement d essai', $entry->properties['old']['name']);
        $this->assertSame(1, $entry->properties['registrations']);
        $this->assertStringNotContainsString('Aya Kouame', json_encode($entry->properties));
    }

    public function test_les_evenements_d_une_autre_organisation_ne_sont_pas_touches(): void
    {
        $other = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Autre Organisation');
        $foreign = $other->asCurrent(fn () => Event::factory()->published()->create(['name' => 'Evenement d ailleurs']));

        $this->artisan('convive:erase-events', ['tenant' => $this->tenant->slug, '--keep' => [$this->latest->id], '--force' => true, '--no-interaction' => true])
            ->assertSuccessful();

        $this->assertSame(1, $other->asCurrent(fn () => Event::whereKey($foreign->id)->count()));
    }
}
