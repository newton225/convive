<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\PaymentChannel;
use App\Http\Middleware\EnforceAbsoluteSessionLifetime;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Tenants\PaymentAccountChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le delai de 24 heures des comptes de versement part de la premiere publication (decision du
 * proprietaire du projet, 2026-10-07) : avant, aucun invite ne voit de compte, un changement
 * s'applique tout de suite apres un apercu et une confirmation ; apres, le delai s'applique pour
 * toujours, meme une fois tous les evenements clotures.
 */
class PaymentAccountFirstPublicationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
    }

    private function actingAsConfirmed(User $user): static
    {
        $this->actingAs($user);
        $this->session([
            'auth.password_confirmed_at' => now()->getTimestamp(),
            'auth.two_factor_confirmed_at' => now()->getTimestamp(),
            EnforceAbsoluteSessionLifetime::SessionKey => now()->getTimestamp(),
        ]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Wave principal',
            'channel' => PaymentChannel::Wave->value,
            'account_number' => '+225 07 00 00 00 01',
            'holder_name' => 'Association Convive',
            'instructions' => null,
            'is_active' => true,
        ], $overrides);
    }

    private function account(): PaymentAccount
    {
        return $this->tenant->asCurrent(fn () => PaymentAccount::firstOrFail());
    }

    private function publishedTenant(): void
    {
        $this->tenant->forceFill(['first_published_at' => now()])->save();
    }

    public function test_avant_la_premiere_publication_un_compte_cree_et_confirme_s_applique_tout_de_suite(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload(['confirmed' => true]))
            ->assertRedirect();

        $account = $this->account();

        $this->assertFalse($account->hasPendingChange());
        $this->assertSame('+225 07 00 00 00 01', $account->account_number);
        $this->assertTrue($account->isPubliclyVisible());
    }

    public function test_avant_la_premiere_publication_la_confirmation_est_exigee(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload())
            ->assertSessionHasErrors('confirmed');

        $this->assertFalse($this->tenant->asCurrent(fn () => PaymentAccount::exists()));
    }

    public function test_avant_la_premiere_publication_une_modification_confirmee_s_applique_tout_de_suite(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload(['confirmed' => true]));

        $this->actingAsConfirmed($this->owner)
            ->patch(route('tenants.payment-accounts.update', [$this->tenant, $this->account()]), $this->payload([
                'account_number' => '+225 07 99 99 99 99',
                'confirmed' => true,
            ]))
            ->assertRedirect();

        $account = $this->account();

        $this->assertSame('+225 07 99 99 99 99', $account->account_number);
        $this->assertFalse($account->hasPendingChange());
    }

    public function test_avant_la_premiere_publication_l_alerte_part_quand_meme(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload(['confirmed' => true]));

        Notification::assertSentTo($this->owner, PaymentAccountChanged::class);
    }

    public function test_un_libelle_change_avant_publication_ne_demande_pas_de_confirmation(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload(['confirmed' => true]));

        $this->actingAsConfirmed($this->owner)
            ->patch(route('tenants.payment-accounts.update', [$this->tenant, $this->account()]), $this->payload(['label' => 'Wave du bureau']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Wave du bureau', $this->account()->label);
    }

    public function test_publier_un_premier_evenement_demarre_le_delai(): void
    {
        $this->tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'registration_number' => 'CI-ABJ-2021-B-04812',
            'tax_number' => '1804517 K',
            'address' => 'Rue des Jardins',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $this->tenant->update(['subdomain' => 'convive-ci']);

        $event = $this->tenant->asCurrent(function () {
            $account = PaymentAccount::factory()->create();
            $event = Event::factory()->create();
            $event->paymentAccounts()->sync([$account->id]);

            return $event;
        });

        $this->assertNull($this->tenant->fresh()->first_published_at);

        $this->actingAs($this->owner)
            ->post(route('tenants.events.publish', [$this->tenant, $event]))
            ->assertRedirect();

        $this->assertNotNull($this->tenant->fresh()->first_published_at);
    }

    public function test_apres_la_premiere_publication_une_creation_attend_vingt_quatre_heures(): void
    {
        $this->publishedTenant();

        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload())
            ->assertSessionHasNoErrors();

        $account = $this->account();

        $this->assertTrue($account->hasPendingChange());
        $this->assertNull($account->account_number);
        $this->assertFalse($account->isPubliclyVisible());
    }

    public function test_le_delai_reste_meme_quand_tous_les_evenements_sont_clotures(): void
    {
        $this->tenant->asCurrent(fn () => Event::factory()->closed()->create(['published_at' => now()->subMonth()]));
        $this->publishedTenant();

        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload(['confirmed' => true]));

        $this->assertTrue($this->account()->hasPendingChange());
    }

    public function test_une_organisation_qui_avait_deja_publie_garde_le_delai(): void
    {
        // Ouverte avant la colonne : la publication se lit sur ses evenements, puis est retenue.
        $this->tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->account()->hasPendingChange());
        $this->assertNotNull($this->tenant->fresh()->first_published_at);
    }

    public function test_apres_publication_un_second_proprietaire_valide_mais_jamais_le_demandeur(): void
    {
        $this->publishedTenant();
        $second = User::factory()->withTwoFactor()->create();
        $this->joinAsOwner($this->tenant, $second);

        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.store', $this->tenant), $this->payload());

        $this->actingAsConfirmed($this->owner)
            ->post(route('tenants.payment-accounts.approve', [$this->tenant, $this->account()]))
            ->assertForbidden();
        $this->assertTrue($this->account()->hasPendingChange());

        $this->actingAsConfirmed($second)
            ->post(route('tenants.payment-accounts.approve', [$this->tenant, $this->account()]))
            ->assertRedirect();
        $this->assertFalse($this->account()->hasPendingChange());
    }

    public function test_la_confirmation_de_premiere_publication_annonce_le_delai(): void
    {
        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.paymentDelayActive', false));

        $this->publishedTenant();

        $this->actingAs($this->owner)
            ->get(route('tenants.events.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('tenant.paymentDelayActive', true));
    }

    public function test_la_page_des_comptes_dit_si_le_delai_s_applique(): void
    {
        $this->actingAsConfirmed($this->owner)
            ->get(route('tenants.payment-accounts.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('delayActive', false));

        $this->publishedTenant();

        $this->actingAsConfirmed($this->owner)
            ->get(route('tenants.payment-accounts.index', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->where('delayActive', true));
    }
}
