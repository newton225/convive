<?php

namespace Tests\Feature\Public;

use App\Actions\Registrations\PhoneVerification;
use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\Registrations\PhoneVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Verification du telephone par code avant la reservation (SECURITY.md C3, decision du
 * 2026-09-27) : reglage par evenement, desactive par defaut. Active, le formulaire enregistre un
 * brouillon qui ne bloque aucune place, envoie un code par SMS, et ne reserve qu'une fois le
 * code saisi : un robot sans vrais telephones ne peut plus bloquer la salle.
 */
class PhoneVerificationTest extends TestCase
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
    }

    private function event(bool $verification): Event
    {
        return $this->tenant->asCurrent(function () use ($verification) {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create(['rule_phone_verification' => $verification]);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function url(Event $event, string $suffix = ''): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return ($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$event->public_token.$suffix;
    }

    /**
     * @return TestResponse<Response>
     */
    private function register(Event $event, string $phone = '+225 07 07 12 34 56'): TestResponse
    {
        return $this->post($this->url($event, '/register'), [
            'name' => 'Aya Kouassi',
            'phone' => $phone,
            'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
        ]);
    }

    private function sentCode(): string
    {
        $code = null;

        Notification::assertSentOnDemand(PhoneVerificationCode::class, function (PhoneVerificationCode $notification, array $channels, AnonymousNotifiable $notifiable) use (&$code) {
            $code = $notification->code;

            // Par SMS seulement (decision du 2026-10-03), au numero sous sa forme unique.
            return $channels === ['sms'] && $notifiable->routeNotificationFor('sms') === '+2250707123456';
        });

        return (string) $code;
    }

    private function registration(): Registration
    {
        return $this->tenant->asCurrent(fn () => Registration::latest('id')->firstOrFail());
    }

    public function test_desactivee_par_defaut_la_reservation_est_immediate(): void
    {
        Notification::fake();

        // Relu en base dans le contexte du locataire : hors tenancy, la connexion `tenant` n'existe
        // plus et `fresh()` echouerait. La valeur par defaut est celle de la colonne.
        $this->assertFalse($this->tenant->asCurrent(
            fn () => Event::factory()->published()->create()->fresh()->rule_phone_verification,
        ));

        $this->register($this->event(false));

        $this->assertSame(RegistrationStatus::Held, $this->registration()->status);
        Notification::assertNothingSent();
    }

    public function test_activee_le_formulaire_envoie_un_code_sans_bloquer_de_place(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $response = $this->register($event);

        $registration = $this->registration();
        $this->assertSame(RegistrationStatus::Draft, $registration->status);
        $this->assertNull($registration->held_until);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->sentCode());
        $this->assertStringContainsString('/verify', (string) $response->headers->get('Location'));
    }

    public function test_le_bon_code_declenche_la_reservation(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $verifyUrl = (string) $this->register($event)->headers->get('Location');

        $this->post($verifyUrl, ['code' => $this->sentCode()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $registration = $this->registration();
        $this->assertSame(RegistrationStatus::Held, $registration->status);
        $this->assertNotNull($registration->phone_verified_at);
    }

    public function test_un_mauvais_code_est_refuse_et_cinq_echecs_invalident_le_code(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $verifyUrl = (string) $this->register($event)->headers->get('Location');
        $code = $this->sentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->post($verifyUrl, ['code' => $wrong])->assertSessionHasErrors('code');
        }

        // Meme le bon code ne passe plus : il faut en demander un nouveau.
        $this->post($verifyUrl, ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame(RegistrationStatus::Draft, $this->registration()->status);
    }

    public function test_un_code_expire_est_refuse(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $verifyUrl = (string) $this->register($event)->headers->get('Location');
        $code = $this->sentCode();

        $this->travel(11)->minutes();

        $this->post($verifyUrl, ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_un_nouveau_code_peut_etre_demande(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $verifyUrl = (string) $this->register($event)->headers->get('Location');

        $this->post($verifyUrl.'/resend')->assertRedirect();

        Notification::assertSentOnDemandTimes(PhoneVerificationCode::class, 2);
    }

    public function test_un_numero_etranger_reserve_sans_code(): void
    {
        // Decision du 2026-10-03 (option a) : le SMS part par Orange Cote d'Ivoire, qui ne garantit
        // pas l'envoi a l'etranger. Un invite etranger ne doit pas rester bloque devant un code qui
        // n'arrivera jamais : la verification ne vaut que pour les numeros ivoiriens.
        Notification::fake();

        $this->register($this->event(true), '+33 6 12 34 56 78')->assertSessionHasNoErrors();

        $this->assertSame(RegistrationStatus::Held, $this->registration()->status);
        Notification::assertNothingSent();
    }

    public function test_le_code_tient_en_un_seul_sms(): void
    {
        // Au-dela de 160 caracteres, Orange facture deux SMS par code.
        $message = (new PhoneVerificationCode('482915'))->toSms(null);

        $this->assertStringContainsString('482915', $message);
        $this->assertLessThanOrEqual(160, mb_strlen($message));
    }

    public function test_un_meme_numero_ne_recoit_pas_plus_de_cinq_codes_par_heure(): void
    {
        // Chaque SMS est paye : sans plafond par numero, un robot qui recommence l'inscription en
        // boucle viderait le credit d'Orange et inonderait le telephone d'un tiers.
        Notification::fake();
        $event = $this->event(true);

        $this->tenant->asCurrent(function () use ($event) {
            $verification = app(PhoneVerification::class);

            for ($i = 0; $i < 6; $i++) {
                $registration = Registration::factory()->create(['event_id' => $event->id, 'phone' => '+2250707123456', 'status' => RegistrationStatus::Draft]);
                $sent = $verification->send($registration);

                $this->assertSame($i < PhoneVerification::MaxCodesPerHour, $sent);
            }
        });

        Notification::assertSentOnDemandTimes(PhoneVerificationCode::class, PhoneVerification::MaxCodesPerHour);
    }

    public function test_au_dela_du_plafond_l_invite_est_prevenu_au_lieu_d_attendre_un_code(): void
    {
        Notification::fake();
        $event = $this->event(true);

        $this->tenant->asCurrent(function () {
            for ($i = 0; $i < PhoneVerification::MaxCodesPerHour; $i++) {
                RateLimiter::hit(PhoneVerification::limiterKey('+2250707123456'), 3600);
            }
        });

        $this->register($event)->assertSessionHasErrors('phone');

        $this->assertSame(0, $this->tenant->asCurrent(fn () => Registration::count()));
        Notification::assertNothingSent();
    }
}
