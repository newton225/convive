<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\LegalForm;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Annonce sur le site produit (CLAUDE.md, « Annonce sur le site produit », decision du
 * 2026-09-22) : opt-in, jamais automatique a la publication, retirable a tout moment.
 */
class ShowcaseAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function publishableTenant(User $owner): Tenant
    {
        $tenant = $this->tenantOwnedBy($owner);

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

        $tenant->asCurrent(fn () => PaymentAccount::factory()->create());

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(Tenant $tenant, array $attributes = []): Event
    {
        $event = $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->create($attributes);
            $event->paymentAccounts()->sync(
                PaymentAccount::publiclyVisible()->pluck('id')->all(),
            );

            return $event->fresh();
        });

        $this->actingAs($tenant->owner())->post(route('tenants.events.publish', [$tenant, $event]));

        return $tenant->asCurrent(fn () => $event->fresh());
    }

    public function test_annoncer_un_evenement_publie_cree_une_ligne_de_vitrine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.announce', [$tenant, $event]))
            ->assertRedirect();

        $showcased = ShowcaseEvent::where('tenant_id', $tenant->id)->where('event_id', $event->id)->first();

        $event = $tenant->asCurrent(fn () => $event->fresh());

        $this->assertNotNull($showcased);
        $this->assertSame($event->name, $showcased->name);
        $this->assertSame('Convive', $showcased->organisation_name);
        $this->assertStringContainsString($event->public_token, $showcased->public_url);
        $this->assertTrue($event->isAnnounced());
    }

    public function test_annoncer_n_est_jamais_automatique_a_la_publication(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->assertFalse($event->isAnnounced());
        $this->assertSame(0, ShowcaseEvent::where('tenant_id', $tenant->id)->count());
    }

    public function test_un_evenement_non_publie_ne_peut_pas_etre_annonce(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $tenant->asCurrent(fn () => Event::factory()->create());

        $this->actingAs($owner)
            ->post(route('tenants.events.announce', [$tenant, $event]))
            ->assertSessionHasErrors('event');

        $this->assertSame(0, ShowcaseEvent::where('tenant_id', $tenant->id)->count());
    }

    public function test_retirer_l_annonce_supprime_la_ligne_de_vitrine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.announce', [$tenant, $event]));

        $this->actingAs($owner)
            ->delete(route('tenants.events.announce.withdraw', [$tenant, $event]))
            ->assertRedirect();

        $this->assertSame(0, ShowcaseEvent::where('tenant_id', $tenant->id)->count());
        $this->assertFalse($tenant->asCurrent(fn () => $event->fresh())->isAnnounced());
    }

    public function test_retirer_l_annonce_reste_possible_apres_cloture(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.announce', [$tenant, $event]));
        $this->actingAs($owner)->post(route('tenants.events.close', [$tenant, $event]));

        $this->actingAs($owner)
            ->delete(route('tenants.events.announce.withdraw', [$tenant, $event]))
            ->assertRedirect();

        $this->assertSame(0, ShowcaseEvent::where('tenant_id', $tenant->id)->count());
    }

    public function test_cloturer_ne_retire_pas_automatiquement_de_la_vitrine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.announce', [$tenant, $event]));
        $this->actingAs($owner)->post(route('tenants.events.close', [$tenant, $event]));

        $this->assertSame(1, ShowcaseEvent::where('tenant_id', $tenant->id)->count());
        $this->assertSame(EventStatus::Closed, $tenant->asCurrent(fn () => $event->fresh())->status);
    }

    public function test_renommer_un_evenement_annonce_met_a_jour_la_vitrine(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.announce', [$tenant, $event]));

        $this->actingAs($owner)->patch(route('tenants.events.update', [$tenant, $event]), [
            'name' => 'Nouveau nom de la soiree',
            'subtitle' => null,
            'starts_at' => $event->starts_at->toDateTimeString(),
            'venue' => $event->venue,
            'venue_address' => $event->venue_address,
            'table_count' => $event->table_count,
            'seats_per_table' => $event->seats_per_table,
            'price_per_person' => $event->price_per_person,
            'companion_limit' => $event->companion_limit,
            'registration_deadline' => $event->registration_deadline->toDateTimeString(),
            'purge_at' => $event->purge_at->toDateTimeString(),
            'invitations_send_at' => $event->invitations_send_at->toDateTimeString(),
            'hold_duration_minutes' => $event->hold_duration_minutes,
            'payment_accounts' => $tenant->asCurrent(fn () => PaymentAccount::publiclyVisible()->pluck('id')->all()),
        ]);

        $showcased = ShowcaseEvent::where('tenant_id', $tenant->id)->where('event_id', $event->id)->firstOrFail();

        $this->assertSame('Nouveau nom de la soiree', $showcased->name);
    }

    public function test_la_vitrine_montre_le_visuel_de_l_evenement_annonce(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->actingAs($owner)->post(route('tenants.events.announce', [$tenant, $event]));
        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('visuel.png', 800, 400),
        ]);

        $this->get(route('showcase.index'))
            ->assertInertia(fn ($page) => $page->whereType('events.0.visualUrl', 'string'));

        $this->actingAs($owner)->delete(route('tenants.events.visual.destroy', [$tenant, $event]));

        $this->get(route('showcase.index'))
            ->assertInertia(fn ($page) => $page->where('events.0.visualUrl', null));
    }

    public function test_sans_la_permission_dediee_impossible_d_annoncer(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsUpdate]);

        $this->actingAs($member)
            ->post(route('tenants.events.announce', [$tenant, $event]))
            ->assertForbidden();
    }
}
