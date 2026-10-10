<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le recapitulatif de la premiere page sur la page du dossier : l'invite relit ce qu'il a declare
 * (personnes, unites, tarifs, telephone, email) avant de payer.
 */
class RegistrationSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function publishableTenant(User $owner): Tenant
    {
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $tenant->brandingOrCreate()->fill([
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

        $tenant->update(['subdomain' => 'convive-ci']);
        $tenant->asCurrent(fn () => PaymentAccount::factory()->create());

        return $tenant->fresh();
    }

    public function test_deux_personnes_au_meme_tarif_forment_une_seule_ligne_de_detail(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);

        $registration = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $standard = EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'Standard', 'price' => 10000]);

            $registration = Registration::factory()->held()->create([
                'event_id' => $event->id,
                'price_category_id' => $standard->id,
                'amount_due' => 20000,
            ]);

            RegistrationCompanion::factory()->create([
                'registration_id' => $registration->id,
                'unit_id' => Unit::factory()->create()->id,
                'price_category_id' => $standard->id,
                'position' => 1,
            ]);

            return $registration;
        });

        $this->get($tenant->asCurrent(fn () => $registration->signedResumeUrl()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('registration.breakdown', 1)
                ->where('registration.breakdown.0.name', 'Standard')
                ->where('registration.breakdown.0.count', 2)
                ->where('registration.breakdown.0.subtotal', 20000));
    }

    public function test_la_page_du_dossier_porte_les_personnes_leurs_tarifs_et_les_coordonnees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);

        $registration = $tenant->asCurrent(function () {
            $event = Event::factory()->published()->create();
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());
            $vip = EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'VIP', 'price' => 30000]);
            $free = EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'Gratos', 'price' => 0]);

            $registration = Registration::factory()->held()->create([
                'event_id' => $event->id,
                'price_category_id' => $vip->id,
                'phone' => '+2250707123456',
                'email' => 'aya@example.com',
                'amount_due' => 30000,
            ]);

            RegistrationCompanion::factory()->create([
                'registration_id' => $registration->id,
                'unit_id' => Unit::factory()->create()->id,
                'price_category_id' => $free->id,
                'name' => 'Moussa Traore',
                'position' => 1,
            ]);

            return $registration;
        });

        $this->get($tenant->asCurrent(fn () => $registration->signedResumeUrl()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('registration.phone', '+225 07 07 12 34 56')
                ->where('registration.email', 'aya@example.com')
                ->where('registration.priceCategory.name', 'VIP')
                ->where('registration.priceCategory.price', 30000)
                ->where('registration.companions.0.name', 'Moussa Traore')
                ->where('registration.companions.0.priceCategory.name', 'Gratos')
                ->where('registration.companions.0.priceCategory.price', 0)
                ->has('registration.breakdown', 2)
                ->where('registration.breakdown.0.name', 'VIP')
                ->where('registration.breakdown.0.count', 1)
                ->where('registration.breakdown.0.subtotal', 30000)
                ->where('registration.breakdown.1.name', 'Gratos')
                ->where('registration.breakdown.1.subtotal', 0)
                ->has('event.startsAt'));
    }
}
