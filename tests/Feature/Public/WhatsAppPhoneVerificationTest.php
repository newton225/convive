<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Contracts\WhatsAppSender;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WhatsAppPhoneCheck;
use App\Notifications\Registrations\PhoneVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\WebhookClient\Models\WebhookCall;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Verification du telephone par un message WhatsApp de l'invite (decision du proprietaire du
 * projet, 2026-10-04) : Meta refuse le modele d'authentification a une entreprise non verifiee, et
 * le SMS est mis de cote. L'invite envoie, depuis son WhatsApp, un message pre-rempli portant un
 * code au numero de Convive ; Meta le transmet au serveur, qui verifie que le message vient bien du
 * numero saisi. C'est la possession du telephone qui est prouvee, sans rien envoyer a l'invite.
 */
class WhatsAppPhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const AppSecret = 'secret-application-meta';

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.meta' => [
                'access_token' => 'jeton-meta',
                'phone_number_id' => '1098765432',
                'api_version' => 'v23.0',
                'template_language' => 'fr',
            ],
            'services.whatsapp.inbound' => [
                'number' => '+2250700000000',
                'verify_token' => 'jeton-de-verification',
                'app_secret' => self::AppSecret,
            ],
        ]);
        $this->app->forgetInstance(WhatsAppSender::class);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.reponse']]])]);

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
            $event = Event::factory()->published()->create(['rule_phone_verification' => true]);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function publicUrl(string $suffix): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return ($appUrl['scheme'] ?? 'http').'://convive-ci.'.config('convive.public_domain').$port
            .'/e/'.$this->event->public_token.$suffix;
    }

    /**
     * Register and return the address of the verification page.
     */
    private function register(string $phone = '+225 07 07 12 34 56'): string
    {
        return (string) $this->post($this->publicUrl('/register'), [
            'name' => 'Aya Kouassi',
            'phone' => $phone,
            'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
        ])->headers->get('Location');
    }

    private function registration(): Registration
    {
        return $this->tenant->asCurrent(fn () => Registration::latest('id')->firstOrFail());
    }

    private function code(): string
    {
        return WhatsAppPhoneCheck::latest('id')->firstOrFail()->code;
    }

    /**
     * Send what Meta sends when a person writes to the business number.
     *
     * @return TestResponse<Response>
     */
    private function incoming(string $from, string $text, ?string $secret = self::AppSecret): TestResponse
    {
        $body = (string) json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '1108085104900352',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '2250700000000', 'phone_number_id' => '1098765432'],
                        'contacts' => [['profile' => ['name' => 'Aya'], 'wa_id' => $from]],
                        'messages' => [[
                            'from' => $from,
                            'id' => 'wamid.'.bin2hex(random_bytes(6)),
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ]);

        return $this->call('POST', route('webhook-client-whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, (string) $secret),
        ], $body);
    }

    public function test_aucun_code_n_est_envoye_l_invite_recoit_un_lien_whatsapp_pret_a_envoyer(): void
    {
        Notification::fake();

        $this->get($this->register())->assertInertia(fn (Assert $page) => $page
            ->component('public/registration-verify')
            ->where('method', 'whatsapp')
            ->where('whatsappLink', fn (string $link) => str_starts_with($link, 'https://wa.me/2250700000000?text=')
                && str_contains(urldecode($link), $this->code())));

        Notification::assertNothingSent();
        $this->assertSame(RegistrationStatus::Draft, $this->registration()->status);
    }

    public function test_le_message_venu_du_numero_saisi_verifie_le_telephone_et_l_invite_recoit_une_reponse(): void
    {
        $this->register();

        $this->incoming('2250707123456', 'Code Convive : '.$this->code())->assertOk();

        $this->assertNotNull($this->registration()->phone_verified_at);
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'graph.facebook.com')
            && $request['to'] === '2250707123456'
            && $request['type'] === 'text');
    }

    public function test_le_code_envoye_depuis_un_autre_numero_ne_verifie_rien(): void
    {
        $this->register();

        $this->incoming('2250505999999', 'Code Convive : '.$this->code())->assertOk();

        $this->assertNull($this->registration()->phone_verified_at);
    }

    public function test_un_code_expire_ne_verifie_rien(): void
    {
        $this->register();
        $code = $this->code();

        $this->travel(11)->minutes();
        $this->incoming('2250707123456', 'Code Convive : '.$code);

        $this->assertNull($this->registration()->phone_verified_at);
    }

    public function test_l_ancien_format_ivoirien_a_huit_chiffres_est_reconnu(): void
    {
        // WhatsApp connait encore bien des numeros ivoiriens sous leur forme d'avant 2021 :
        // 225 07 07 12 34 56 arrive comme 225 07 12 34 56 (vu le 2026-10-03 sur un vrai envoi).
        $this->register();

        $this->incoming('22507123456', 'Code Convive : '.$this->code());

        $this->assertNotNull($this->registration()->phone_verified_at);
    }

    public function test_un_message_sans_signature_valide_est_refuse(): void
    {
        $this->register();

        $this->incoming('2250707123456', 'Code Convive : '.$this->code(), 'mauvais-secret')->assertServerError();

        $this->assertNull($this->registration()->phone_verified_at);
    }

    public function test_meta_valide_l_adresse_avec_le_jeton_convenu(): void
    {
        $this->get(route('webhooks.whatsapp.challenge', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'jeton-de-verification', 'hub_challenge' => '1158201444']))
            ->assertOk()
            ->assertSee('1158201444');

        $this->get(route('webhooks.whatsapp.challenge', ['hub_mode' => 'subscribe', 'hub_verify_token' => 'autre', 'hub_challenge' => '1158201444']))
            ->assertForbidden();
    }

    public function test_les_accuses_de_reception_ne_sont_pas_conserves(): void
    {
        // Meta previent aussi de chaque message livre ou lu : sans message entrant, rien a garder.
        $body = (string) json_encode(['object' => 'whatsapp_business_account', 'entry' => [['changes' => [['field' => 'messages', 'value' => ['statuses' => [['status' => 'delivered']]]]]]]]);

        $this->call('POST', route('webhook-client-whatsapp'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, self::AppSecret),
        ], $body)->assertOk();

        $this->assertSame(0, WebhookCall::count());
    }

    public function test_une_fois_verifie_l_invite_continue_et_sa_place_est_reservee(): void
    {
        $verifyPage = $this->register();
        $this->incoming('2250707123456', 'Code Convive : '.$this->code());

        $this->get($verifyPage)->assertInertia(fn (Assert $page) => $page->where('whatsappVerified', true));

        $this->post($verifyPage.'/whatsapp')->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(RegistrationStatus::Held, $this->registration()->status);
    }

    public function test_continuer_avant_le_message_est_refuse(): void
    {
        $verifyPage = $this->register();

        $this->post($verifyPage.'/whatsapp')->assertSessionHasErrors('whatsapp');

        $this->assertSame(RegistrationStatus::Draft, $this->registration()->status);
    }

    public function test_un_numero_etranger_est_aussi_verifie_par_whatsapp(): void
    {
        // WhatsApp joint tous les pays : l'exemption des numeros etrangers ne valait que pour le SMS.
        $this->register('+33 6 12 34 56 78');

        $this->assertSame(RegistrationStatus::Draft, $this->registration()->status);

        $this->incoming('33612345678', 'Code Convive : '.$this->code());

        $this->assertNotNull($this->registration()->phone_verified_at);
    }

    public function test_un_nouveau_code_remplace_l_ancien(): void
    {
        $verifyPage = $this->register();
        $old = $this->code();

        $this->post($verifyPage.'/resend')->assertRedirect();
        $this->assertNotSame($old, $this->code());

        $this->incoming('2250707123456', 'Code Convive : '.$old);

        $this->assertNull($this->registration()->phone_verified_at);
    }
}
