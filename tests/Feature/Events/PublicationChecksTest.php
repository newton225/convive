<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\EventStatus;
use App\Enums\LegalForm;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Ce qu'un evenement doit avoir avant d'etre publie, puis annonce sur le site produit (decision du
 * proprietaire du projet, 2026-10-07) : un lieu et une date a venir pour publier ; pour la vitrine,
 * un evenement publie, ouvert, a venir et illustre. Un evenement clos ou passe quitte la vitrine.
 */
class PublicationChecksTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->tenant->brandingOrCreate()->fill([
            'display_name' => 'Convive',
            'legal_name' => 'Association Convive',
            'legal_form' => LegalForm::Association,
            'representative_name' => 'Aya Kouassi',
            'city' => 'Abidjan',
            'country' => 'CI',
            'phone' => '+225 07 07 12 34 56',
        ])->save();
        $this->tenant->update(['subdomain' => 'convive-ci']);
        $this->tenant->asCurrent(fn () => PaymentAccount::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(array $attributes = []): Event
    {
        return $this->tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->create($attributes);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function fresh(Event $event): Event
    {
        return $this->tenant->asCurrent(fn () => $event->fresh());
    }

    private function withVisual(Event $event): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.visual.store', [$this->tenant, $event]), [
            'file' => UploadedFile::fake()->image('visuel.png', 800, 400),
        ]);
    }

    private function publish(Event $event): void
    {
        $this->actingAs($this->owner)->post(route('tenants.events.publish', [$this->tenant, $event]));
    }

    public function test_un_evenement_sans_lieu_ne_se_publie_pas(): void
    {
        $event = $this->event(['venue' => null]);

        $this->assertContains('venue', $this->tenant->asCurrent(fn () => $event->missingBeforePublishing()));

        $this->actingAs($this->owner)
            ->post(route('tenants.events.publish', [$this->tenant, $event]))
            ->assertSessionHasErrors('event');

        $this->assertFalse($this->fresh($event)->isPublished());
    }

    public function test_un_evenement_deja_passe_ne_se_publie_pas(): void
    {
        $event = $this->event(['starts_at' => now()->subDay()]);

        $this->assertContains('date_past', $this->tenant->asCurrent(fn () => $event->missingBeforePublishing()));

        $this->publish($event);

        $this->assertFalse($this->fresh($event)->isPublished());
    }

    public function test_un_evenement_complet_se_publie(): void
    {
        $event = $this->event();

        $this->assertSame([], $this->tenant->asCurrent(fn () => $event->missingBeforePublishing()));

        $this->publish($event);

        $this->assertTrue($this->fresh($event)->isPublished());
    }

    public function test_la_fiche_dit_ce_qui_manque_pour_publier(): void
    {
        $event = $this->event(['venue' => null]);

        $this->actingAs($this->owner)
            ->get(route('tenants.events.edit', [$this->tenant, $event]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('event.missingBeforePublishing', ['venue']));
    }

    public function test_un_evenement_sans_visuel_s_annonce_quand_meme(): void
    {
        $event = $this->event();
        $this->publish($event);

        // Sans visuel, la carte de la vitrine dessine une affiche (decision du 2026-10-08).
        $this->actingAs($this->owner)
            ->post(route('tenants.events.announce', [$this->tenant, $event]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ShowcaseEvent::count());
    }

    public function test_un_evenement_publie_ouvert_a_venir_et_illustre_s_annonce(): void
    {
        $event = $this->event();
        $this->withVisual($event);
        $this->publish($event);

        $this->actingAs($this->owner)
            ->post(route('tenants.events.announce', [$this->tenant, $event]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ShowcaseEvent::count());
    }

    public function test_un_evenement_clos_ne_s_annonce_pas(): void
    {
        $event = $this->event();
        $this->withVisual($event);
        $this->publish($event);
        $this->tenant->asCurrent(fn () => $event->fresh()->forceFill(['status' => EventStatus::Closed])->save());

        $this->actingAs($this->owner)
            ->post(route('tenants.events.announce', [$this->tenant, $event]))
            ->assertSessionHasErrors('event');

        $this->assertContains('closed', $this->tenant->asCurrent(fn () => $event->fresh()->missingBeforeAnnouncing()));
    }

    public function test_cloturer_un_evenement_le_retire_de_la_vitrine(): void
    {
        $event = $this->event();
        $this->withVisual($event);
        $this->publish($event);
        $this->actingAs($this->owner)->post(route('tenants.events.announce', [$this->tenant, $event]));

        $this->actingAs($this->owner)
            ->post(route('tenants.events.close', [$this->tenant, $event]))
            ->assertRedirect();

        $this->assertSame(0, ShowcaseEvent::count());
        $this->assertFalse($this->fresh($event)->isAnnounced());
    }

    public function test_la_vitrine_n_affiche_plus_un_evenement_passe(): void
    {
        ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gala passe', 'starts_at' => now()->subDay()]);
        ShowcaseEvent::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Gala a venir', 'starts_at' => now()->addWeek()]);

        $this->get(route('showcase.index'))->assertInertia(fn (Assert $page) => $page
            ->has('events', 1)
            ->where('events.0.name', 'Gala a venir'));
    }
}
