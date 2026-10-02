<?php

namespace Tests\Feature\Public;

use App\Actions\Tenants\CreateTenant;
use App\Enums\LegalForm;
use App\Enums\RegistrationStatus;
use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    /**
     * Une organisation reellement publiable : identite legale complete, sous-domaine, et un
     * compte de versement visible.
     */
    private function publishableTenant(User $owner, string $subdomain = 'convive-ci'): Tenant
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

        $tenant->update(['subdomain' => $subdomain]);

        $tenant->asCurrent(fn () => PaymentAccount::factory()->create());

        return $tenant->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(Tenant $tenant, array $attributes = []): Event
    {
        return $tenant->asCurrent(function () use ($attributes) {
            $event = Event::factory()->published()->create($attributes);
            $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());

            return $event->fresh();
        });
    }

    private function urlFor(string $subdomain, string $token, string $suffix = ''): string
    {
        $appUrl = parse_url((string) config('app.url'));
        $scheme = $appUrl['scheme'] ?? 'http';
        $port = isset($appUrl['port']) ? ':'.$appUrl['port'] : '';
        $domain = $subdomain.'.'.config('convive.public_domain');

        return "{$scheme}://{$domain}{$port}/e/{$token}{$suffix}";
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Tenant $tenant): array
    {
        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        return [
            'name' => 'Aya Kouassi',
            'phone' => '+225 07 07 12 34 56',
            'unit_id' => $unitId,
            'companions' => [],
        ];
    }

    /**
     * Un evenement a une seule place, deja occupee : complet des le depart.
     */
    private function fullEvent(Tenant $tenant): Event
    {
        $event = $this->publishedEvent($tenant, ['tables' => [1, 1]]);

        $tenant->asCurrent(fn () => Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => 1,
        ]));

        return $event;
    }

    public function test_on_ne_peut_pas_rejoindre_la_liste_d_attente_d_un_evenement_qui_a_de_la_place(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant);

        $this->post($this->urlFor($tenant->subdomain, $event->public_token, '/waitlist'), $this->validPayload($tenant))
            ->assertRedirect($this->urlFor($tenant->subdomain, $event->public_token));

        $this->assertSame(0, $tenant->asCurrent(fn () => WaitlistEntry::count()));
    }

    public function test_on_peut_rejoindre_la_liste_d_attente_quand_l_evenement_est_complet(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->fullEvent($tenant);

        $response = $this->post(
            $this->urlFor($tenant->subdomain, $event->public_token, '/waitlist'),
            $this->validPayload($tenant),
        );

        $response->assertRedirect();

        $entry = $tenant->asCurrent(fn () => WaitlistEntry::first());
        $this->assertNotNull($entry);
        $this->assertSame(WaitlistStatus::Waiting, $entry->status);
        $this->assertSame('Aya Kouassi', $entry->name);

        $this->assertStringContainsString('/waitlist/', $response->headers->get('Location'));
        $this->assertStringNotContainsString("/waitlist/{$entry->id}", $response->headers->get('Location'));
    }

    public function test_la_position_reflete_l_ordre_d_arrivee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->fullEvent($tenant);

        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        [$first, $second, $third] = $tenant->asCurrent(fn () => [
            WaitlistEntry::factory()->create(['event_id' => $event->id, 'unit_id' => $unitId]),
            WaitlistEntry::factory()->create(['event_id' => $event->id, 'unit_id' => $unitId]),
            WaitlistEntry::factory()->create(['event_id' => $event->id, 'unit_id' => $unitId]),
        ]);

        $tenant->asCurrent(function () use ($first, $second, $third) {
            $this->assertSame(1, $first->position());
            $this->assertSame(2, $second->position());
            $this->assertSame(3, $third->position());
        });
    }

    public function test_le_lien_de_finalisation_est_valable_six_heures(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->fullEvent($tenant);

        $entry = $tenant->asCurrent(fn () => WaitlistEntry::factory()->invited()->create(['event_id' => $event->id]));

        $tenant->asCurrent(function () use ($entry) {
            $this->assertTrue($entry->expires_at->betweenIncluded(now()->addHours(5), now()->addHours(6)));
        });
    }

    public function test_finaliser_cree_une_inscription_reservee(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->publishedEvent($tenant, ['tables' => [1, 1], 'price_per_person' => 15000]);

        $unitId = $tenant->asCurrent(fn () => Unit::where('name', 'QODESH')->value('id'));

        $plainToken = WaitlistEntry::generateResumeToken();
        $tenant->asCurrent(fn () => WaitlistEntry::factory()->invited()->create([
            'event_id' => $event->id,
            'name' => 'Kofi Diallo',
            'unit_id' => $unitId,
            'resume_token_hash' => WaitlistEntry::hashResumeToken($plainToken),
        ]));

        $response = $this->post($this->urlFor($tenant->subdomain, $event->public_token, "/waitlist/{$plainToken}/finalize"));

        $response->assertRedirect();
        $this->assertStringContainsString('/register/', $response->headers->get('Location'));

        $tenant->asCurrent(function () use ($event) {
            $registration = Registration::where('event_id', $event->id)->first();
            $this->assertNotNull($registration);
            $this->assertSame(RegistrationStatus::Held, $registration->status);
            $this->assertSame('Kofi Diallo', $registration->name);

            $entry = WaitlistEntry::first();
            $this->assertSame(WaitlistStatus::Converted, $entry->status);
        });
    }

    public function test_finaliser_une_entree_qui_n_a_pas_ete_invitee_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $event = $this->fullEvent($tenant);

        $plainToken = WaitlistEntry::generateResumeToken();
        $tenant->asCurrent(fn () => WaitlistEntry::factory()->create([
            'event_id' => $event->id,
            'resume_token_hash' => WaitlistEntry::hashResumeToken($plainToken),
        ]));

        $this->post($this->urlFor($tenant->subdomain, $event->public_token, "/waitlist/{$plainToken}/finalize"))
            ->assertNotFound();
    }

    public function test_une_entree_d_un_autre_evenement_recoit_404(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->publishableTenant($owner);
        $eventA = $this->publishedEvent($tenant, ['name' => 'Evenement A']);
        $eventB = $this->fullEvent($tenant);

        $plainToken = WaitlistEntry::generateResumeToken();
        $tenant->asCurrent(fn () => WaitlistEntry::factory()->create([
            'event_id' => $eventB->id,
            'resume_token_hash' => WaitlistEntry::hashResumeToken($plainToken),
        ]));

        $this->get($this->urlFor($tenant->subdomain, $eventA->public_token, "/waitlist/{$plainToken}"))
            ->assertNotFound();
    }
}
