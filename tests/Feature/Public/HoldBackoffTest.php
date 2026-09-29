<?php

namespace Tests\Feature\Public;

use App\Actions\Registrations\HoldRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Delai croissant apres des reservations expirees repetees sur le meme numero (SECURITY.md C3) :
 * un script qui laisse expirer ses reservations en boucle pour bloquer la salle doit attendre de
 * plus en plus longtemps avant d'en reprendre une.
 */
class HoldBackoffTest extends TestCase
{
    use RefreshDatabase;

    private const Phone = '+225 07 07 12 34 56';

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
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
        $this->tenant = $tenant->fresh();

        $this->event = $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create();
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function expiredRegistration(string $phone = self::Phone): Registration
    {
        return $this->tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $this->event->id,
            'phone' => $phone,
            'status' => RegistrationStatus::Expired,
            'held_until' => now()->subMinute(),
        ]));
    }

    private function register(): TestResponse
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $url = ($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$this->event->public_token.'/register';

        return $this->post($url, [
            'name' => 'Aya Kouassi',
            'phone' => self::Phone,
            'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
        ]);
    }

    public function test_une_seule_reservation_expiree_ne_retarde_pas_la_suivante(): void
    {
        $this->expiredRegistration();

        $this->assertNull($this->tenant->asCurrent(fn () => Registration::phoneBackoffUntil($this->event, self::Phone)));
    }

    public function test_deux_reservations_expirees_imposent_une_attente(): void
    {
        $this->expiredRegistration();
        // Le meme numero, autrement ecrit : l'indicatif entre parentheses.
        $this->expiredRegistration('(+225) 07 07 12 34 56');

        $until = $this->tenant->asCurrent(fn () => Registration::phoneBackoffUntil($this->event, self::Phone));

        $this->assertNotNull($until);
        $this->assertTrue($until->isFuture());
    }

    public function test_l_attente_double_a_chaque_expiration_supplementaire(): void
    {
        $this->expiredRegistration();
        $this->expiredRegistration();
        $shortWait = $this->tenant->asCurrent(fn () => Registration::phoneBackoffUntil($this->event, self::Phone));

        $this->expiredRegistration();
        $longerWait = $this->tenant->asCurrent(fn () => Registration::phoneBackoffUntil($this->event, self::Phone));

        $this->assertEqualsWithDelta(
            now()->diffInMinutes($shortWait) * 2,
            now()->diffInMinutes($longerWait),
            2,
        );
    }

    public function test_un_autre_numero_n_est_pas_retarde(): void
    {
        $this->expiredRegistration();
        $this->expiredRegistration();

        $this->assertNull($this->tenant->asCurrent(fn () => Registration::phoneBackoffUntil($this->event, '+225 05 05 99 88 77')));
    }

    public function test_le_formulaire_refuse_le_numero_pendant_l_attente_puis_l_accepte(): void
    {
        $this->expiredRegistration();
        $this->expiredRegistration();

        $this->register()->assertSessionHasErrors('phone');
        $this->assertSame(2, $this->tenant->asCurrent(fn () => Registration::count()));

        $this->travel(11)->minutes();

        $this->register()->assertSessionHasNoErrors();
        $this->assertSame(RegistrationStatus::Held, $this->tenant->asCurrent(fn () => Registration::latest('id')->first()->status));
    }

    public function test_relancer_une_reservation_expiree_compte_l_expiration(): void
    {
        $registration = $this->expiredRegistration();

        $this->tenant->asCurrent(function () use ($registration) {
            app(HoldRegistration::class)->handle($this->event, $registration);

            $this->assertSame(1, $registration->fresh()->lapsed_holds_count);
        });
    }
}
