<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Enums\TenantPermission;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * README ecran 13 : le visuel propre a l'evenement, distinct des fichiers de marque de
 * l'organisation. Memes controles que `tests/Feature/Tenants/BrandFileTest.php` : type et
 * contenu inspectes, taille limitee, range par locataire, URL signee.
 */
class EventVisualTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaRoot = storage_path('framework/testing/tenant-media-'.Str::random(8));

        config(['filesystems.disks.tenant_media.root' => $this->mediaRoot]);
        Storage::forgetDisk('tenant_media');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);

        parent::tearDown();
    }

    private function tenantOwnedBy(User $user, string $name = 'Association Convive'): Tenant
    {
        return app(CreateTenant::class)->handle($user, $name);
    }

    private function eventOf(Tenant $tenant): Event
    {
        return $tenant->asCurrent(fn () => Event::factory()->create());
    }

    public function test_le_proprietaire_depose_le_visuel_de_l_evenement(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.visual.store', [$tenant, $event]), [
                'file' => UploadedFile::fake()->image('visuel.png', 800, 400),
            ])
            ->assertRedirect();

        $this->assertNotNull(
            $tenant->asCurrent(fn () => $event->fresh()->getFirstMedia(Event::VisualCollection)),
        );
    }

    public function test_un_nouveau_depot_remplace_le_precedent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('premier.png'),
        ]);
        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('second.png'),
        ]);

        $count = $tenant->asCurrent(fn () => $event->fresh()->getMedia(Event::VisualCollection)->count());

        $this->assertSame(1, $count);
    }

    public function test_un_svg_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $svg = UploadedFile::fake()->createWithContent(
            'visuel.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->actingAs($owner)
            ->post(route('tenants.events.visual.store', [$tenant, $event]), ['file' => $svg])
            ->assertSessionHasErrors('file');
    }

    public function test_un_fichier_de_plus_de_cinq_mega_octets_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)
            ->post(route('tenants.events.visual.store', [$tenant, $event]), [
                'file' => UploadedFile::fake()->image('lourd.jpg')->size(5121),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_un_membre_sans_la_permission_ne_depose_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->post(route('tenants.events.visual.store', [$tenant, $event]), [
                'file' => UploadedFile::fake()->image('visuel.png'),
            ])
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_depot(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->actingAs($stranger)
            ->post(route('tenants.events.visual.store', [$tenant, $event]), [
                'file' => UploadedFile::fake()->image('visuel.png'),
            ])
            ->assertNotFound();
    }

    public function test_les_fichiers_sont_ranges_par_locataire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('visuel.png'),
        ]);

        $media = $tenant->asCurrent(fn () => $event->fresh()->getFirstMedia(Event::VisualCollection));

        $this->assertStringStartsWith("tenants/{$tenant->id}/visual/", $media->getPathRelativeToRoot());
    }

    public function test_le_proprietaire_supprime_le_visuel(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('visuel.png'),
        ]);

        $this->actingAs($owner)
            ->delete(route('tenants.events.visual.destroy', [$tenant, $event]))
            ->assertRedirect();

        $this->assertNull(
            $tenant->asCurrent(fn () => $event->fresh()->getFirstMedia(Event::VisualCollection)),
        );
    }

    public function test_un_membre_sans_la_permission_ne_supprime_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);
        $event = $this->eventOf($tenant);

        $this->actingAs($owner)->post(route('tenants.events.visual.store', [$tenant, $event]), [
            'file' => UploadedFile::fake()->image('visuel.png'),
        ]);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::EventsView]);

        $this->actingAs($member)
            ->delete(route('tenants.events.visual.destroy', [$tenant, $event]))
            ->assertForbidden();

        $this->assertNotNull(
            $tenant->asCurrent(fn () => $event->fresh()->getFirstMedia(Event::VisualCollection)),
        );
    }
}
