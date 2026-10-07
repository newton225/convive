<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un evenement gratuit, tarif 0 (decision du proprietaire du projet, 2026-10-07) : l'inscription
 * est confirmee directement, sans preuve ni decompte, dans la limite des places ; aucun compte de
 * versement n'est exige pour publier.
 */
class FreeEventRegistrationTest extends TestCase
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
     * @param  array<string, mixed>  $attributes
     */
    private function event(array $attributes = []): Event
    {
        return $this->tenant->asCurrent(fn () => Event::factory()->open()->create(['price_per_person' => 0, ...$attributes]));
    }

    private function draft(Event $event, int $partySize = 1): Registration
    {
        return $this->tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $event->id,
            'status' => RegistrationStatus::Draft,
            'party_size' => $partySize,
            'amount_due' => $event->price_per_person * $partySize,
        ]));
    }

    public function test_une_inscription_a_un_evenement_gratuit_est_confirmee_directement(): void
    {
        $event = $this->event();
        $registration = $this->draft($event);

        $held = $this->tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $registration));

        $this->assertTrue($held);

        $this->tenant->asCurrent(function () use ($registration) {
            $fresh = $registration->fresh();

            $this->assertSame(RegistrationStatus::Confirmed, $fresh->status);
            $this->assertNull($fresh->held_until);
            // Le billet part comme apres la validation d'une preuve.
            $this->assertTrue($fresh->tickets()->exists());
        });
    }

    public function test_un_evenement_gratuit_complet_refuse_une_inscription_de_plus(): void
    {
        $event = $this->event(['tables' => [1, 1]]);
        $first = $this->draft($event);
        $second = $this->draft($event);

        $this->assertTrue($this->tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $first)));
        $this->assertFalse($this->tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $second)));

        $this->assertSame(RegistrationStatus::Draft, $this->tenant->asCurrent(fn () => $second->fresh())->status);
    }

    public function test_un_evenement_payant_reste_en_attente_de_preuve(): void
    {
        $event = $this->event(['price_per_person' => 15000]);
        $registration = $this->draft($event);

        $this->tenant->asCurrent(fn () => app(HoldRegistration::class)->handle($event, $registration));

        $this->assertSame(RegistrationStatus::Held, $this->tenant->asCurrent(fn () => $registration->fresh())->status);
    }

    public function test_un_evenement_gratuit_se_publie_sans_compte_de_versement(): void
    {
        $this->tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $this->tenant->update(['subdomain' => 'convive-ci']);

        $free = $this->event();
        $paid = $this->event(['price_per_person' => 15000]);

        $this->tenant->asCurrent(function () use ($free, $paid) {
            $this->assertNotContains('payment_account', $free->missingBeforePublishing());
            $this->assertContains('payment_account', $paid->missingBeforePublishing());
            $this->assertFalse(PaymentAccount::exists());
        });
    }
}
