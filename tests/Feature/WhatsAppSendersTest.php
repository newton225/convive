<?php

namespace Tests\Feature;

use App\Contracts\WhatsAppSender;
use App\Support\LogWhatsAppSender;
use App\Support\WhatsApp\MetaWhatsAppSender;
use App\Support\WhatsApp\TwilioWhatsAppSender;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Tests\TestCase;

/**
 * L'envoi WhatsApp reel (decision du proprietaire du projet, 2026-10-03) : Twilio pour commencer,
 * et Meta en direct quand son compte sera pret, par un simple reglage (`WHATSAPP_DRIVER`), sans
 * toucher au code. Les modeles de messages exiges par WhatsApp se declarent eux aussi par reglage.
 */
class WhatsAppSendersTest extends TestCase
{
    /**
     * @param  array<string, string>  $templates
     */
    private function twilio(array $templates = []): void
    {
        config([
            'services.whatsapp.driver' => 'twilio',
            'services.whatsapp.twilio' => [
                'account_sid' => 'AC0123456789',
                'auth_token' => 'jeton-secret',
                'from' => '+14155238886',
            ],
            'services.whatsapp.templates' => $templates,
        ]);
        $this->app->forgetInstance(WhatsAppSender::class);
    }

    /**
     * @param  array<string, string>  $templates
     */
    private function meta(array $templates = []): void
    {
        config([
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.meta' => [
                'access_token' => 'jeton-meta',
                'phone_number_id' => '1098765432',
                'api_version' => 'v23.0',
                'template_language' => 'fr',
            ],
            'services.whatsapp.templates' => $templates,
        ]);
        $this->app->forgetInstance(WhatsAppSender::class);
    }

    private function template(): WhatsAppTemplate
    {
        return new WhatsAppTemplate('proof_reminder', ['Aya Kouassi', 'Diner de gala', 'https://convive.test/l']);
    }

    public function test_sans_reglage_les_messages_restent_au_journal(): void
    {
        config(['services.whatsapp.driver' => null]);
        $this->app->forgetInstance(WhatsAppSender::class);

        $sender = app(WhatsAppSender::class);

        $this->assertInstanceOf(LogWhatsAppSender::class, $sender);
        $this->assertFalse($sender->delivers());
    }

    public function test_twilio_sans_ses_identifiants_retombe_sur_le_journal(): void
    {
        config(['services.whatsapp.driver' => 'twilio', 'services.whatsapp.twilio.auth_token' => null]);
        $this->app->forgetInstance(WhatsAppSender::class);

        $this->assertInstanceOf(LogWhatsAppSender::class, app(WhatsAppSender::class));
    }

    public function test_twilio_envoie_le_texte_quand_aucun_modele_n_est_declare(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->twilio();

        $sender = app(WhatsAppSender::class);
        $this->assertInstanceOf(TwilioWhatsAppSender::class, $sender);
        $this->assertTrue($sender->delivers());

        $sender->send('+2250707123456', 'Bonjour Aya', $this->template());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.twilio.com/2010-04-01/Accounts/AC0123456789/Messages.json'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('AC0123456789:jeton-secret'))
            && $request['From'] === 'whatsapp:+14155238886'
            && $request['To'] === 'whatsapp:+2250707123456'
            && $request['Body'] === 'Bonjour Aya'
            && ! isset($request['ContentSid']));
    }

    public function test_twilio_envoie_le_modele_declare_avec_ses_variables(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->twilio(['proof_reminder' => 'HX0123']);

        app(WhatsAppSender::class)->send('+2250707123456', 'Bonjour Aya', $this->template());

        Http::assertSent(fn (Request $request) => $request['ContentSid'] === 'HX0123'
            && json_decode($request['ContentVariables'], true) === ['1' => 'Aya Kouassi', '2' => 'Diner de gala', '3' => 'https://convive.test/l']
            && ! isset($request['Body']));
    }

    public function test_meta_envoie_le_texte_quand_aucun_modele_n_est_declare(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->meta();

        $sender = app(WhatsAppSender::class);
        $this->assertInstanceOf(MetaWhatsAppSender::class, $sender);
        $this->assertTrue($sender->delivers());

        $sender->send('+2250707123456', 'Bonjour Aya', $this->template());

        Http::assertSent(fn (Request $request) => $request->url() === 'https://graph.facebook.com/v23.0/1098765432/messages'
            && $request->hasHeader('Authorization', 'Bearer jeton-meta')
            && $request['messaging_product'] === 'whatsapp'
            && $request['to'] === '2250707123456'
            && $request['type'] === 'text'
            && $request['text']['body'] === 'Bonjour Aya');
    }

    public function test_meta_envoie_le_modele_declare_avec_ses_parametres(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->meta(['proof_reminder' => 'convive_rappel_preuve']);

        app(WhatsAppSender::class)->send('+2250707123456', 'Bonjour Aya', $this->template());

        Http::assertSent(fn (Request $request) => $request['type'] === 'template'
            && $request['template']['name'] === 'convive_rappel_preuve'
            && $request['template']['language']['code'] === 'fr'
            && $request['template']['components'][0]['type'] === 'body'
            && array_column($request['template']['components'][0]['parameters'], 'text') === ['Aya Kouassi', 'Diner de gala', 'https://convive.test/l']);
    }

    public function test_meta_envoie_le_code_une_seconde_fois_pour_le_bouton_copier_du_modele_d_authentification(): void
    {
        // Chez Meta, un modele d'authentification porte un bouton « copier le code » qui attend le
        // code en parametre : sans lui, Meta refuse le message et l'invite ne recoit jamais son code.
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->meta(['phone_code' => 'convive_code']);

        app(WhatsAppSender::class)->send('+2250707123456', 'Votre code', WhatsAppTemplate::authentication('phone_code', '482915'));

        Http::assertSent(fn (Request $request) => $request['template']['components'] === [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => '482915']]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => '482915']]],
        ]);
    }

    public function test_twilio_envoie_le_code_comme_unique_variable_du_modele_d_authentification(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->twilio(['phone_code' => 'HX0999']);

        app(WhatsAppSender::class)->send('+2250707123456', 'Votre code', WhatsAppTemplate::authentication('phone_code', '482915'));

        Http::assertSent(fn (Request $request) => json_decode($request['ContentVariables'], true) === ['1' => '482915']);
    }

    public function test_une_variable_de_modele_tient_sur_une_seule_ligne(): void
    {
        // Meta refuse une variable avec retour a la ligne, tabulation ou plus de quatre espaces.
        $template = new WhatsAppTemplate('registration_cancelled', ["Aya\nKouassi", "Diner\tde   gala", "  Salle\r\n\r\nfermee      ce soir  "]);

        $this->assertSame(['Aya Kouassi', 'Diner de gala', 'Salle fermee ce soir'], $template->parameters);
    }

    public function test_passer_de_twilio_a_meta_ne_demande_qu_un_reglage(): void
    {
        Http::fake();

        $this->twilio();
        $this->assertInstanceOf(TwilioWhatsAppSender::class, app(WhatsAppSender::class));

        $this->meta();
        $this->assertInstanceOf(MetaWhatsAppSender::class, app(WhatsAppSender::class));
    }

    public function test_le_canal_transmet_le_modele_que_la_notification_declare(): void
    {
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);
        $this->twilio(['proof_reminder' => 'HX0123']);

        $notification = new class extends Notification
        {
            /**
             * @return array<int, string>
             */
            public function via(mixed $notifiable): array
            {
                return ['whatsapp'];
            }

            public function toWhatsApp(mixed $notifiable): string
            {
                return 'Bonjour Aya';
            }

            public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
            {
                return new WhatsAppTemplate('proof_reminder', ['Aya Kouassi', 'Diner de gala', 'https://convive.test/l']);
            }
        };

        NotificationFacade::route('whatsapp', '+2250707123456')->notifyNow($notification);

        Http::assertSent(fn (Request $request) => $request['ContentSid'] === 'HX0123' && $request['To'] === 'whatsapp:+2250707123456');
    }

    public function test_un_refus_du_service_fait_echouer_l_envoi_pour_qu_il_soit_relance(): void
    {
        // Un echec silencieux laisserait croire le message parti : l'exception fait echouer la tache,
        // visible et relancable depuis la console (« Envois en echec »).
        Http::fake(['api.twilio.com/*' => Http::response(['message' => 'Invalid To'], 400)]);
        $this->twilio();

        $this->expectException(RequestException::class);

        app(WhatsAppSender::class)->send('+2250707123456', 'Bonjour Aya');
    }
}
