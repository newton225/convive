<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Inscription supprimee » (README ecran 11) : la page generique que voit un invite dont le dossier
 * a ete purge. Elle ne dit rien d'une inscription precise, seulement de l'evenement.
 */
class RegistrationDeletedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Association Convive');
        $this->tenant->update(['subdomain' => 'convive-ci']);
    }

    private function urlFor(string $token): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';

        return "{$scheme}://convive-ci.".config('convive.public_domain')."{$port}/e/{$token}/deleted";
    }

    public function test_la_page_propose_de_s_inscrire_de_nouveau_quand_l_evenement_accepte_du_monde(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->published()->create([
            'name' => 'Gala',
            'table_count' => 10,
            'seats_per_table' => 10,
        ]));

        $this->get($this->urlFor($event->public_token))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('public/registration-deleted')
                ->where('event.name', 'Gala')
                ->where('event.acceptsRegistrations', true)
                ->where('event.isFull', false)
                ->where('token', $event->public_token)
                ->has('tenant.colors.primary'),
            );
    }

    public function test_la_page_ne_porte_aucune_donnee_d_inscription(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->published()->create());

        $this->get($this->urlFor($event->public_token))
            ->assertInertia(fn ($page) => $page->missing('registration')->missing('resume'));
    }

    public function test_un_jeton_inconnu_recoit_404(): void
    {
        $this->get($this->urlFor(str_repeat('a', 64)))->assertNotFound();
    }

    public function test_un_evenement_non_publie_recoit_404_comme_un_jeton_inconnu(): void
    {
        $event = $this->tenant->asCurrent(fn () => Event::factory()->create(['public_token' => str_repeat('b', 64), 'published_at' => null]));

        $this->get($this->urlFor((string) $event->public_token))->assertNotFound();
    }

    public function test_le_jeton_d_une_autre_organisation_recoit_404(): void
    {
        $other = app(CreateTenant::class)->handle(User::factory()->withTwoFactor()->create(), 'Autre Association');
        $other->update(['subdomain' => 'autre-ci']);
        $foreign = $other->asCurrent(fn () => Event::factory()->published()->create());

        $this->get($this->urlFor($foreign->public_token))->assertNotFound();
    }
}
