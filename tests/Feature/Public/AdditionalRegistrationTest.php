<?php

namespace Tests\Feature\Public;

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
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Une seconde inscription avec le meme numero (decision du proprietaire du projet, 2026-10-03) :
 * possible seulement quand la precedente est terminee. Confirmee, l'invite en est prevenu et doit
 * confirmer qu'il veut une inscription additionnelle ; encore en cours, elle reste refusee
 * (SECURITY.md C3 : une seule reservation en cours par numero).
 */
class AdditionalRegistrationTest extends TestCase
{
    use RefreshDatabase;

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

    /**
     * @param  array<string, string>  $extra
     * @return TestResponse<Response>
     */
    private function register(array $extra = []): TestResponse
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return $this->post(($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$this->event->public_token.'/register', [
                'name' => 'Aya Kouassi',
                'phone' => '+225 07 07 12 34 56',
                'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
                'companions' => [],
                ...$extra,
            ]);
    }

    private function existing(RegistrationStatus $status): void
    {
        $this->tenant->asCurrent(fn () => Registration::factory()->create([
            'event_id' => $this->event->id,
            'phone' => '+2250707123456',
            'status' => $status,
            'held_until' => $status === RegistrationStatus::Held ? now()->addHour() : null,
        ]));
    }

    private function registrations(): int
    {
        return $this->tenant->asCurrent(fn () => Registration::where('event_id', $this->event->id)->count());
    }

    public function test_un_numero_deja_confirme_est_d_abord_prevenu(): void
    {
        $this->existing(RegistrationStatus::Confirmed);

        $this->register()->assertSessionHasErrors('additional_registration');

        $this->assertSame(1, $this->registrations());
    }

    public function test_apres_confirmation_une_inscription_additionnelle_est_creee(): void
    {
        $this->existing(RegistrationStatus::Confirmed);

        $this->register(['confirm_additional' => '1'])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->registrations());
    }

    public function test_une_reservation_en_cours_reste_refusee_meme_avec_confirmation(): void
    {
        $this->existing(RegistrationStatus::Held);

        $this->register(['confirm_additional' => '1'])->assertSessionHasErrors('phone');

        $this->assertSame(1, $this->registrations());
    }

    public function test_une_preuve_en_attente_reste_refusee_meme_avec_confirmation(): void
    {
        $this->existing(RegistrationStatus::ProofSubmitted);

        $this->register(['confirm_additional' => '1'])->assertSessionHasErrors('phone');

        $this->assertSame(1, $this->registrations());
    }

    public function test_une_inscription_annulee_ne_demande_aucune_confirmation(): void
    {
        $this->existing(RegistrationStatus::Cancelled);

        $this->register()->assertSessionHasNoErrors();

        $this->assertSame(2, $this->registrations());
    }
}
