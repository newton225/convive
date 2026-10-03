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
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Un invite qui revient sur le formulaire (bouton « retour » du navigateur) alors que sa
 * reservation court encore (demande du proprietaire du projet, 2026-10-03) : le formulaire lui
 * propose de la reprendre, au lieu de le laisser bute sur « une reservation est deja en cours
 * pour ce numero » sans moyen d'y revenir.
 */
class OngoingReservationTest extends TestCase
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

    private function event(): Event
    {
        return $this->tenant->asCurrent(function () {
            PaymentAccount::factory()->create();
            $event = Event::factory()->published()->create();
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
    private function register(Event $event): TestResponse
    {
        return $this->post($this->url($event, '/register'), [
            'name' => 'Aya Kouassi',
            'phone' => '+225 07 07 12 34 56',
            'unit_id' => $this->tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id')),
            'companions' => [],
        ]);
    }

    private function ongoingUrl(Event $event): ?string
    {
        $url = null;

        $this->get($this->url($event, '/register'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$url) {
                $url = $page->toArray()['props']['ongoingReservationUrl'];
            });

        return $url;
    }

    public function test_de_retour_sur_le_formulaire_l_invite_peut_reprendre_sa_reservation(): void
    {
        $event = $this->event();
        $reservation = (string) $this->register($event)->headers->get('Location');

        $this->assertSame($reservation, $this->ongoingUrl($event));
        $this->get($reservation)->assertOk();
    }

    public function test_le_retour_arriere_redemande_le_formulaire_au_serveur(): void
    {
        // Sans cela, le bouton « retour » reaffiche la copie du formulaire gardee par le
        // navigateur, d'avant l'inscription, sans proposition de reprise : la copie est chiffree,
        // et l'inscription efface ces copies (mecanisme officiel d'Inertia).
        $event = $this->event();

        $this->get($this->url($event, '/register'))
            ->assertInertia(fn (Assert $page) => $this->assertTrue($page->toArray()['encryptHistory'] ?? false));

        $reservation = (string) $this->register($event)->headers->get('Location');

        $this->get($reservation)
            ->assertInertia(fn (Assert $page) => $this->assertTrue($page->toArray()['clearHistory'] ?? false));
    }

    public function test_sans_reservation_en_cours_rien_n_est_propose(): void
    {
        $this->assertNull($this->ongoingUrl($this->event()));
    }

    public function test_un_autre_navigateur_ne_voit_pas_la_reservation(): void
    {
        $event = $this->event();
        $this->register($event);

        $this->flushSession();

        $this->assertNull($this->ongoingUrl($event));
    }

    public function test_une_reservation_expiree_n_est_plus_proposee(): void
    {
        $event = $this->event();
        $this->register($event);

        $this->tenant->asCurrent(fn () => Registration::query()->update(['held_until' => now()->subMinute()]));

        $this->assertNull($this->ongoingUrl($event));
    }

    public function test_une_preuve_deposee_reste_proposee_jusqu_a_la_validation(): void
    {
        $event = $this->event();
        $this->register($event);

        $this->tenant->asCurrent(fn () => Registration::query()->update(['status' => RegistrationStatus::ProofSubmitted]));
        $this->assertNotNull($this->ongoingUrl($event));

        $this->tenant->asCurrent(fn () => Registration::query()->update(['status' => RegistrationStatus::Confirmed]));
        $this->assertNull($this->ongoingUrl($event));
    }

    public function test_une_reservation_d_un_autre_evenement_n_est_pas_proposee(): void
    {
        $first = $this->event();
        $second = $this->event();
        $this->register($first);

        $this->assertNull($this->ongoingUrl($second));
    }

    public function test_le_jeton_de_reprise_n_est_pas_garde_en_clair_dans_la_session(): void
    {
        // La session vit dans la base centrale : le jeton y est chiffre, comme il n'est garde
        // qu'en empreinte sur l'inscription.
        $event = $this->event();
        $reservation = (string) $this->register($event)->headers->get('Location');
        $resume = basename((string) parse_url($reservation, PHP_URL_PATH));

        $this->assertStringNotContainsString($resume, serialize(session()->all()));
    }
}
