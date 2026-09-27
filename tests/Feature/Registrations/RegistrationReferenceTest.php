<?php

namespace Tests\Feature\Registrations;

use App\Actions\Registrations\CreateRegistration;
use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\RegistrationReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reference de dossier lisible (prototype Convive.dc.html, « SP-2026-0008 ») : initiales de
 * l'organisation, annee, numero. Affichage seulement (SECURITY.md H3) : jamais un moyen d'acceder
 * a un dossier, qui reste adresse par son jeton de reprise.
 */
class RegistrationReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($owner, 'Association des Soldats du Palais');
    }

    private function register(Event $event): Registration
    {
        return app(CreateRegistration::class)->handle($event, [
            'name' => 'Kouadio Jessica',
            'phone' => '+225 07 07 12 34 56',
            'email' => null,
            'unit_id' => Unit::where('name', 'Aucune')->value('id'),
            'companions' => [],
        ])['registration'];
    }

    public function test_les_initiales_ignorent_les_mots_de_liaison(): void
    {
        $this->assertSame('SP', RegistrationReference::initials('Association des Soldats du Palais'));
        $this->assertSame('EE', RegistrationReference::initials('Église ÉLIAKIM'));
        $this->assertSame('CV', RegistrationReference::initials('   '));
    }

    public function test_chaque_inscription_recoit_une_reference_qui_se_suit_sur_l_annee(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 27));

        [$first, $second] = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();

            return [$this->register($event), $this->register($event)];
        });

        $this->assertSame('SP-2026-0001', $first->reference);
        $this->assertSame('SP-2026-0002', $second->reference);
    }

    public function test_un_numero_n_est_jamais_reutilise_apres_une_suppression(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 27));

        $third = $this->tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            $this->register($event);
            $this->register($event)->delete();

            return $this->register($event);
        });

        $this->assertSame('SP-2026-0003', $third->reference);
    }

    public function test_le_compteur_repart_a_un_chaque_annee(): void
    {
        $this->travelTo(now()->setDate(2026, 12, 31));
        $this->tenant->asCurrent(fn () => $this->register(Event::factory()->open()->create()));

        $this->travelTo(now()->setDate(2027, 1, 1));
        $next = $this->tenant->asCurrent(fn () => $this->register(Event::factory()->open()->create()));

        $this->assertSame('SP-2027-0001', $next->reference);
    }
}
