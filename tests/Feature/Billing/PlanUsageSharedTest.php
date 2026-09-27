<?php

namespace Tests\Feature\Billing;

use App\Actions\Tenants\CreateTenant;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Le plan de l'organisation courante et son usage, visibles dans le menu lateral et le selecteur
 * d'espace (prototype Convive.dc.html : « Plan Association · 2 evenements actifs sur 5 »).
 */
class PlanUsageSharedTest extends TestCase
{
    use RefreshDatabase;

    public function test_chaque_page_recoit_le_plan_courant_et_ses_evenements_actifs(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');
        $tenant->asCurrent(fn () => Event::factory()->open()->create());

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentPlan.name', 'Essentiel')
                ->where('currentPlan.activeEvents', 1)
                ->where('currentPlan.maxActiveEvents', 1)
                ->where('tenants', fn ($tenants) => collect($tenants)->every(fn ($item) => is_string($item['planName']))));
    }
}
