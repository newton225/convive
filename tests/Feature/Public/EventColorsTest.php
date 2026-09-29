<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les couleurs personnalisees d'un evenement tiennent sur tout son parcours invite, pas seulement
 * sur la page de l'evenement : sans cela, l'invite passait des couleurs de l'evenement a celles de
 * l'organisation en cliquant sur « S'inscrire ».
 */
class EventColorsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
        $this->tenant->update(['subdomain' => 'convive-ci']);
    }

    private function urlFor(string $token, string $path): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return "{$scheme}://convive-ci.".config('convive.public_domain')."{$port}/e/{$token}{$path}";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(array $attributes = []): Event
    {
        return $this->tenant->asCurrent(fn () => Event::factory()->published()->create([
            'table_count' => 10,
            'seats_per_table' => 10,
            ...$attributes,
        ]));
    }

    public function test_le_formulaire_d_inscription_reprend_les_couleurs_de_l_evenement(): void
    {
        $event = $this->publishedEvent(['primary_color' => '#00e639', 'secondary_color' => '#2a7fc8']);

        $this->get($this->urlFor($event->public_token, '/register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.colors.primary', '#00e639')
                ->where('tenant.colors.secondary', '#2a7fc8'),
            );
    }

    public function test_la_page_inscription_supprimee_reprend_les_couleurs_de_l_evenement(): void
    {
        $event = $this->publishedEvent(['primary_color' => '#00e639', 'secondary_color' => '#2a7fc8']);

        $this->get($this->urlFor($event->public_token, '/deleted'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.colors.primary', '#00e639')
                ->where('tenant.colors.secondary', '#2a7fc8'),
            );
    }

    public function test_sans_personnalisation_les_couleurs_de_l_organisation_s_appliquent(): void
    {
        $event = $this->publishedEvent(['primary_color' => null, 'secondary_color' => null]);

        $this->get($this->urlFor($event->public_token, '/register'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('tenant.colors.primary', '#7b1e3a')
                ->where('tenant.colors.secondary', '#c9a227'),
            );
    }
}
