<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

/**
 * Une reponse limitee renvoie un message utile, jamais un code technique brut (CLAUDE.md,
 * « Limitation de debit »). Reproduit le cas du 2026-09-27 : le sixieme export PDF de l'heure
 * affichait une page blanche « 429 Trop de requetes », sans dire quoi faire ni quand reessayer.
 */
class RateLimitMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Pdf::fake();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    private function exportUrl(): string
    {
        return route('tenants.events.registrations.export.pdf', [$this->tenant, $this->event]);
    }

    private function exhaustExports(): void
    {
        for ($i = 0; $i < AppServiceProvider::ExportsPerHour; $i++) {
            $this->actingAs($this->owner)->get($this->exportUrl())->assertOk();
        }
    }

    public function test_depuis_une_page_la_limite_renvoie_sur_cette_page_avec_un_message_et_le_delai(): void
    {
        $this->exhaustExports();

        $origin = route('tenants.events.registrations.index', [$this->tenant, $this->event]);

        $this->actingAs($this->owner)
            ->from($origin)
            ->get($this->exportUrl())
            ->assertRedirect($origin)
            ->assertInertiaFlash('toast.type', 'error')
            ->assertInertiaFlash('toast.message', trans_choice('common.rate_limit.retry_in', 60, ['minutes' => 60]));
    }

    public function test_sans_page_d_origine_la_limite_affiche_une_page_lisible_avec_le_delai(): void
    {
        $this->exhaustExports();

        $this->actingAs($this->owner)
            ->get($this->exportUrl())
            ->assertStatus(429)
            ->assertSee(__('common.rate_limit.title'))
            ->assertSee('minute');
    }
}
