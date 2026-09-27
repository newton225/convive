<?php

namespace Tests\Feature\Settings;

use App\Actions\Tenants\CreateTenant;
use App\Actions\Tickets\IssueTicket;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Code de scan a 4 chiffres (SECURITY.md M8, decision du 2026-09-27) : chaque membre choisit le
 * sien ; il deverrouille l'ecran de scan apres 5 minutes d'inactivite, meme hors ligne. Le serveur
 * ne garde jamais le code, seulement une empreinte PBKDF2 salee que l'appareil sait verifier.
 */
class ScanPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_choisir_un_code_enregistre_une_empreinte_et_jamais_le_code(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->put(route('scan-pin.update'), ['pin' => '4827', 'pin_confirmation' => '4827'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $verifier = $user->fresh()->scan_pin_verifier;

        $this->assertIsArray($verifier);
        $this->assertStringNotContainsString('4827', (string) json_encode($verifier));
        $this->assertSame(
            $verifier['hash'],
            base64_encode(hash_pbkdf2('sha256', '4827', base64_decode($verifier['salt']), $verifier['iterations'], 32, true)),
        );
    }

    public function test_le_code_fait_exactement_quatre_chiffres_et_doit_etre_confirme(): void
    {
        $user = User::factory()->withTwoFactor()->create();

        $this->actingAs($user)
            ->put(route('scan-pin.update'), ['pin' => '12a4', 'pin_confirmation' => '12a4'])
            ->assertSessionHasErrors('pin');

        $this->actingAs($user)
            ->put(route('scan-pin.update'), ['pin' => '12345', 'pin_confirmation' => '12345'])
            ->assertSessionHasErrors('pin');

        $this->actingAs($user)
            ->put(route('scan-pin.update'), ['pin' => '1234', 'pin_confirmation' => '4321'])
            ->assertSessionHasErrors('pin');

        $this->assertNull($user->fresh()->scan_pin_verifier);
    }

    public function test_l_ecran_de_scan_transmet_l_empreinte_du_seul_agent_connecte(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $event = $tenant->asCurrent(function () {
            $event = Event::factory()->open()->create();
            app(IssueTicket::class)->handle(Registration::factory()->confirmed()->create(['event_id' => $event->id]));

            return $event;
        });

        $this->actingAs($owner)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page->where('scanPin', null));

        $this->actingAs($owner)->put(route('scan-pin.update'), ['pin' => '4827', 'pin_confirmation' => '4827']);

        $this->actingAs($owner)
            ->get(route('tenants.events.scan.index', [$tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('scanPin.iterations', fn ($value) => $value >= 100000)
                ->has('scanPin.salt')
                ->has('scanPin.hash'));
    }

    public function test_un_visiteur_non_connecte_ne_peut_pas_choisir_de_code(): void
    {
        $this->put(route('scan-pin.update'), ['pin' => '4827', 'pin_confirmation' => '4827'])
            ->assertRedirect(route('login'));
    }
}
