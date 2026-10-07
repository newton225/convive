<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Models\ConsoleActionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\ReservationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * La duree de reservation d'un evenement se choisit entre un minimum et un maximum, regles depuis
 * l'ecran Securite de la console (decision du proprietaire du projet, 2026-10-07).
 */
class HoldDurationBoundsTest extends TestCase
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
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Diner de gala 2026',
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'venue' => 'Hotel Ivoire',
            'table_groups' => [['count' => 2, 'seats' => 10]],
            'price_per_person' => 15000,
            'payment_accounts' => [],
        ], $overrides);
    }

    private function bounds(int $min, int $max): void
    {
        $settings = app(ReservationSettings::class);
        $settings->hold_min_minutes = $min;
        $settings->hold_max_minutes = $max;
        $settings->save();
    }

    public function test_une_duree_hors_des_bornes_est_refusee(): void
    {
        $this->bounds(5, 30);

        foreach ([4, 31] as $minutes) {
            $this->actingAs($this->owner)
                ->post(route('tenants.events.store', $this->tenant), $this->payload(['hold_duration_minutes' => $minutes]))
                ->assertSessionHasErrors('hold_duration_minutes');
        }

        $this->actingAs($this->owner)
            ->post(route('tenants.events.store', $this->tenant), $this->payload(['hold_duration_minutes' => 15]))
            ->assertSessionHasNoErrors();
    }

    public function test_le_formulaire_recoit_les_bornes(): void
    {
        $this->bounds(5, 30);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.create', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.holdDurationMin', 5)
                ->where('defaults.holdDurationMax', 30));
    }

    public function test_la_console_regle_les_bornes_et_le_journal_le_garde(): void
    {
        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        $this->actingAs($founder)
            ->put(route('console.security.reservation-bounds.update'), ['min' => 5, 'max' => 45])
            ->assertRedirect(route('console.security'));

        $settings = app(ReservationSettings::class)->refresh();
        $this->assertSame(5, $settings->hold_min_minutes);
        $this->assertSame(45, $settings->hold_max_minutes);
        $this->assertTrue(ConsoleActionLog::where('type', 'reservation_bounds_updated')->exists());
    }

    public function test_un_maximum_sous_le_minimum_ou_hors_plafond_est_refuse(): void
    {
        config(['convive.console.operators' => ['fondateur@convive.test']]);
        $founder = User::factory()->withTwoFactor()->create(['email' => 'fondateur@convive.test']);

        foreach ([['min' => 30, 'max' => 10], ['min' => 0, 'max' => 10], ['min' => 5, 'max' => 2000]] as $bounds) {
            $this->actingAs($founder)
                ->put(route('console.security.reservation-bounds.update'), $bounds)
                ->assertSessionHasErrors();
        }
    }
}
