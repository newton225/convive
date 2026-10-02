<?php

namespace Tests\Feature;

use App\Actions\Registrations\PurgeRegistrations;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Profile;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ce que laisse une suppression derriere elle : aucun fichier orphelin, aucune erreur brute quand
 * la donnee sert encore, aucune organisation sans personne pour la tenir.
 */
class DeletionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('payment_proofs');

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    public function test_la_purge_efface_aussi_la_capture_d_une_preuve_rejetee(): void
    {
        [$event, $path] = $this->tenant->asCurrent(function () {
            $event = Event::factory()->create();
            $registration = Registration::factory()->proofRejected()->create(['event_id' => $event->id]);
            $proof = PaymentProof::factory()->create(['registration_id' => $registration->id]);
            $media = $proof->addMedia(UploadedFile::fake()->image('recu.jpg', 400, 800))
                ->toMediaCollection(PaymentProof::ReceiptCollection);

            return [$event, $media->getPathRelativeToRoot()];
        });

        Storage::disk('payment_proofs')->assertExists($path);

        $this->tenant->asCurrent(fn () => app(PurgeRegistrations::class)->handle($event));

        // La capture porte le nom, le numero et le montant de l'invite : elle part avec lui.
        Storage::disk('payment_proofs')->assertMissing($path);
        $this->tenant->asCurrent(fn () => $this->assertSame(0, PaymentProof::count()));
    }

    public function test_une_unite_portee_par_une_inscription_ne_se_supprime_pas(): void
    {
        $unit = $this->tenant->asCurrent(function () {
            $unit = Unit::firstOrFail();
            Registration::factory()->held()->create(['event_id' => Event::factory()->create()->id, 'unit_id' => $unit->id]);

            return $unit;
        });

        $this->actingAs($this->owner)
            ->delete(route('tenants.units.destroy', [$this->tenant, $unit]))
            ->assertRedirect()
            ->assertSessionHasErrors('unit');

        $this->tenant->asCurrent(fn () => $this->assertNotNull(Unit::find($unit->id)));
    }

    public function test_un_compte_de_versement_qui_a_recu_des_preuves_ne_se_supprime_pas(): void
    {
        $account = $this->tenant->asCurrent(function () {
            $account = PaymentAccount::factory()->create();
            PaymentProof::factory()->create([
                'registration_id' => Registration::factory()->proofSubmitted()->create(['event_id' => Event::factory()->create()->id])->id,
                'payment_account_id' => $account->id,
            ]);

            return $account;
        });

        $this->actingAs($this->owner)
            // Mot de passe et second facteur viennent d'etre confirmes : le test porte sur la suite.
            ->withSession(['auth.password_confirmed_at' => now()->getTimestamp(), 'auth.two_factor_confirmed_at' => now()->getTimestamp()])
            ->delete(route('tenants.payment-accounts.destroy', [$this->tenant, $account]))
            ->assertRedirect()
            ->assertSessionHasErrors('payment_account');

        $this->tenant->asCurrent(fn () => $this->assertNotNull(PaymentAccount::find($account->id)));
    }

    public function test_le_dernier_proprietaire_d_une_organisation_ne_supprime_pas_son_compte(): void
    {
        $this->actingAs($this->owner)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($this->owner->fresh());
    }

    public function test_un_proprietaire_qui_n_est_pas_le_dernier_supprime_son_compte(): void
    {
        Notification::fake();
        $second = User::factory()->withTwoFactor()->create();
        $this->tenant->addMember($second, $this->tenant->run(fn () => Profile::where('name', Profile::Owner)->firstOrFail()));

        $this->actingAs($second)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $this->assertNull($second->fresh());
        // L'organisation garde son autre Proprietaire et n'est pas touchee.
        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    public function test_supprimer_son_compte_programme_l_effacement_de_son_espace_personnel(): void
    {
        Notification::fake();
        $user = User::factory()->withTwoFactor()->create();
        $personal = $user->personalTenant();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasNoErrors();

        $trashed = Tenant::withTrashed()->findOrFail($personal->id);

        $this->assertTrue($trashed->trashed());
        $this->assertNotNull($trashed->deletion_scheduled_at);
    }
}
