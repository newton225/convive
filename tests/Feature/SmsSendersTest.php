<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Support\Sms\HsmsSmsSender;
use App\Support\Sms\LogSmsSender;
use App\Support\Sms\OrangeSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use RuntimeException;
use Tests\TestCase;

/**
 * L'envoi de SMS (decision du proprietaire du projet, 2026-10-03) : le code de verification du
 * telephone, que Meta refuse en WhatsApp tant que l'entreprise n'est pas verifiee, part par SMS :
 * HSMS (choix du proprietaire du projet) ou l'API d'Orange Cote d'Ivoire, au choix du reglage. Sans
 * reglage, le journal, comme pour WhatsApp.
 */
class SmsSendersTest extends TestCase
{
    private function orange(?string $senderName = null): void
    {
        config([
            'services.sms.driver' => 'orange',
            'services.sms.orange' => [
                'client_id' => 'identifiant-orange',
                'client_secret' => 'secret-orange',
                'sender_name' => $senderName,
            ],
        ]);
        $this->app->forgetInstance(SmsSender::class);
        Cache::forget(OrangeSmsSender::TokenCacheKey);
    }

    private function fakeOrange(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response(['token_type' => 'Bearer', 'access_token' => 'jeton-orange', 'expires_in' => 3600]),
            'api.orange.com/smsmessaging/*' => Http::response(['outboundSMSMessageRequest' => ['resourceURL' => 'https://api.orange.com/x']], 201),
        ]);
    }

    public function test_sans_reglage_les_sms_restent_au_journal(): void
    {
        config(['services.sms.driver' => null]);
        $this->app->forgetInstance(SmsSender::class);

        $sender = app(SmsSender::class);

        $this->assertInstanceOf(LogSmsSender::class, $sender);
        $this->assertFalse($sender->delivers());
    }

    public function test_orange_sans_ses_identifiants_retombe_sur_le_journal(): void
    {
        config(['services.sms.driver' => 'orange', 'services.sms.orange.client_secret' => null]);
        $this->app->forgetInstance(SmsSender::class);

        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));
    }

    public function test_orange_obtient_un_jeton_puis_envoie_le_sms(): void
    {
        $this->fakeOrange();
        $this->orange();

        $sender = app(SmsSender::class);
        $this->assertInstanceOf(OrangeSmsSender::class, $sender);
        $this->assertTrue($sender->delivers());

        $sender->send('+2250707123456', 'Convive : votre code est 482915.');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.orange.com/oauth/v3/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('identifiant-orange:secret-orange'))
            && $request['grant_type'] === 'client_credentials');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.orange.com/smsmessaging/v1/outbound/tel%3A%2B2250000/requests'
            && $request->hasHeader('Authorization', 'Bearer jeton-orange')
            && $request['outboundSMSMessageRequest']['address'] === 'tel:+2250707123456'
            && $request['outboundSMSMessageRequest']['senderAddress'] === 'tel:+2250000'
            && $request['outboundSMSMessageRequest']['outboundSMSTextMessage']['message'] === 'Convive : votre code est 482915.'
            && ! isset($request['outboundSMSMessageRequest']['senderName']));
    }

    public function test_le_jeton_orange_sert_tant_qu_il_est_valide(): void
    {
        // Le jeton vaut une heure : en redemander un a chaque code doublerait les appels a Orange.
        $this->fakeOrange();
        $this->orange();

        app(SmsSender::class)->send('+2250707123456', 'Premier');
        app(SmsSender::class)->send('+2250707123456', 'Second');

        Http::assertSentCount(3);
    }

    public function test_le_nom_d_expediteur_accorde_par_orange_est_utilise(): void
    {
        $this->fakeOrange();
        $this->orange('Convive');

        app(SmsSender::class)->send('+2250707123456', 'Convive : votre code est 482915.');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'smsmessaging')
            && $request['outboundSMSMessageRequest']['senderName'] === 'Convive');
    }

    public function test_un_refus_d_orange_fait_echouer_l_envoi(): void
    {
        Http::fake([
            'api.orange.com/oauth/v3/token' => Http::response(['access_token' => 'jeton-orange', 'expires_in' => 3600]),
            'api.orange.com/smsmessaging/*' => Http::response(['requestError' => ['policyException' => ['messageId' => 'POL0001']]], 403),
        ]);
        $this->orange();

        $this->expectException(RequestException::class);

        app(SmsSender::class)->send('+2250707123456', 'Convive : votre code est 482915.');
    }

    private function hsms(): void
    {
        config([
            'services.sms.driver' => 'hsms',
            'services.sms.hsms' => [
                'email' => 'compte@devultraapp.com',
                'password' => 'mot-de-passe-hsms',
                'client_id' => 'client-hsms',
                'client_secret' => 'secret-hsms',
            ],
        ]);
        $this->app->forgetInstance(SmsSender::class);
        Cache::forget(HsmsSmsSender::TokenCacheKey);
    }

    public function test_hsms_sans_ses_identifiants_retombe_sur_le_journal(): void
    {
        config(['services.sms.driver' => 'hsms', 'services.sms.hsms.client_secret' => null]);
        $this->app->forgetInstance(SmsSender::class);

        $this->assertInstanceOf(LogSmsSender::class, app(SmsSender::class));
    }

    public function test_hsms_obtient_un_jeton_puis_envoie_le_sms(): void
    {
        Http::fake([
            'hsms.ci/api/token/' => Http::response(['success' => true, 'message' => 'OK', 'token' => 'jeton-hsms'], 202),
            'hsms.ci/api/envoi-sms' => Http::response(['success' => true, 'message' => 'OK', 'data' => []]),
        ]);
        $this->hsms();

        $sender = app(SmsSender::class);
        $this->assertInstanceOf(HsmsSmsSender::class, $sender);
        $this->assertTrue($sender->delivers());

        $sender->send('+2250707123456', 'Convive : votre code est 482915.');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://hsms.ci/api/token/'
            && $request['email'] === 'compte@devultraapp.com'
            && $request['password'] === 'mot-de-passe-hsms');

        // Le numero avec son indicatif, sans le « + », comme dans l'exemple de HSMS.
        Http::assertSent(fn (Request $request) => $request->url() === 'https://hsms.ci/api/envoi-sms'
            && $request->hasHeader('Authorization', 'Bearer jeton-hsms')
            && $request['clientid'] === 'client-hsms'
            && $request['clientsecret'] === 'secret-hsms'
            && $request['telephone'] === '2250707123456'
            && $request['message'] === 'Convive : votre code est 482915.');
    }

    public function test_hsms_garde_son_jeton_entre_deux_envois(): void
    {
        Http::fake([
            'hsms.ci/api/token/' => Http::response(['success' => true, 'token' => 'jeton-hsms'], 202),
            'hsms.ci/api/envoi-sms' => Http::response(['success' => true, 'message' => 'OK']),
        ]);
        $this->hsms();

        app(SmsSender::class)->send('+2250707123456', 'Premier');
        app(SmsSender::class)->send('+2250707123456', 'Second');

        Http::assertSentCount(3);
    }

    public function test_hsms_redemande_un_jeton_quand_le_sien_est_refuse(): void
    {
        // HSMS ne dit pas combien de temps son jeton vaut : un refus d'authentification en
        // redemande un, une seule fois, plutot que de faire echouer le code.
        Http::fake([
            'hsms.ci/api/token/' => Http::sequence()
                ->push(['success' => true, 'token' => 'ancien-jeton'], 202)
                ->push(['success' => true, 'token' => 'nouveau-jeton'], 202),
            'hsms.ci/api/envoi-sms' => Http::sequence()
                ->push(['success' => false, 'message' => 'Unauthenticated.'], 401)
                ->push(['success' => true, 'message' => 'OK']),
        ]);
        $this->hsms();

        app(SmsSender::class)->send('+2250707123456', 'Code');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://hsms.ci/api/envoi-sms'
            && $request->hasHeader('Authorization', 'Bearer nouveau-jeton'));
    }

    public function test_un_refus_de_hsms_fait_echouer_l_envoi(): void
    {
        // HSMS peut repondre 200 avec « success: false » (credit epuise...) : c'est un echec.
        Http::fake([
            'hsms.ci/api/token/' => Http::response(['success' => true, 'token' => 'jeton-hsms'], 202),
            'hsms.ci/api/envoi-sms' => Http::response(['success' => false, 'message' => 'Solde insuffisant']),
        ]);
        $this->hsms();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Solde insuffisant');

        app(SmsSender::class)->send('+2250707123456', 'Code');
    }

    public function test_le_canal_sms_transmet_le_texte_de_la_notification(): void
    {
        $this->fakeOrange();
        $this->orange();

        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(mixed $notifiable): array
            {
                return ['sms'];
            }

            public function toSms(mixed $notifiable): string
            {
                return 'Convive : votre code est 482915.';
            }
        };

        NotificationFacade::route('sms', '+2250707123456')->notifyNow($notification);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'smsmessaging')
            && $request['outboundSMSMessageRequest']['address'] === 'tel:+2250707123456');
    }
}
