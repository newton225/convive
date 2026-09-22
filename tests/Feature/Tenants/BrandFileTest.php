<?php

namespace Tests\Feature\Tenants;

use App\Actions\Tenants\CreateTenant;
use App\Enums\BrandFile;
use App\Enums\TenantPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BrandFileTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    /**
     * On isole le disque par une racine temporaire plutot qu'avec `Storage::fake()` : le faux
     * disque perd `serve => true`, et donc la signature d'URL que ces tests doivent verifier.
     */
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

    private function upload(Tenant $tenant, User $actor, BrandFile $file, UploadedFile $upload): TestResponse
    {
        return $this->actingAs($actor)->post(
            route('tenants.organisation.files.store', [$tenant, $file->value]),
            ['file' => $upload],
        );
    }

    /**
     * Un JPEG portant un segment EXIF reconnaissable, pour verifier que le reencodage le
     * supprime reellement plutot que de le supposer.
     */
    private function jpegWithExif(string $marker): UploadedFile
    {
        $image = imagecreatetruecolor(60, 60);
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();
        imagedestroy($image);

        $payload = "Exif\x00\x00".$marker;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        $path = tempnam(sys_get_temp_dir(), 'exif').'.jpg';
        file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

        return new UploadedFile($path, 'cachet.jpg', 'image/jpeg', null, true);
    }

    public function test_le_proprietaire_depose_un_logo(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png', 400, 400))
            ->assertRedirect();

        $this->assertNotNull(
            $tenant->fresh()->branding->getFirstMedia(BrandFile::Logo->value),
        );
    }

    public function test_les_quatre_fichiers_de_marque_sont_acceptes(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        foreach (BrandFile::cases() as $file) {
            $this->upload($tenant, $owner, $file, UploadedFile::fake()->image("{$file->value}.png"))
                ->assertRedirect();
        }

        $branding = $tenant->fresh()->branding;

        foreach (BrandFile::cases() as $file) {
            $this->assertNotNull($branding->getFirstMedia($file->value), "Le fichier {$file->value} manque.");
        }
    }

    public function test_un_nouveau_depot_remplace_le_precedent(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('premier.png'));
        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('second.png'));

        $this->assertCount(1, $tenant->fresh()->branding->getMedia(BrandFile::Logo->value));
    }

    public function test_un_svg_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $this->upload($tenant, $owner, BrandFile::Logo, $svg)
            ->assertSessionHasErrors('file');

        $this->assertNull($tenant->fresh()->branding?->getFirstMedia(BrandFile::Logo->value));
    }

    public function test_un_fichier_de_plus_de_cinq_mega_octets_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('lourd.jpg')->size(5121))
            ->assertSessionHasErrors('file');
    }

    public function test_un_fichier_qui_n_est_pas_une_image_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        // Extension et type annonce d'une image, contenu qui n'en est pas une : c'est
        // l'inspection du contenu qui doit trancher, pas ce que declare le client.
        $path = tempnam(sys_get_temp_dir(), 'faux').'.png';
        file_put_contents($path, '<?php echo "salut"; ?>');
        $disguised = new UploadedFile($path, 'logo.png', 'image/png', null, true);

        $this->upload($tenant, $owner, BrandFile::Logo, $disguised)
            ->assertSessionHasErrors('file');
    }

    public function test_les_metadonnees_de_l_image_sont_supprimees(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $marker = 'CONVIVE-METADONNEE-A-SUPPRIMER';

        $this->upload($tenant, $owner, BrandFile::Stamp, $this->jpegWithExif($marker))
            ->assertRedirect();

        $media = $tenant->fresh()->branding->getFirstMedia(BrandFile::Stamp->value);
        $stored = Storage::disk('tenant_media')->get($media->getPathRelativeToRoot());

        $this->assertStringNotContainsString($marker, $stored);
    }

    public function test_les_fichiers_sont_ranges_par_locataire(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png'));

        $media = $tenant->fresh()->branding->getFirstMedia(BrandFile::Logo->value);

        $this->assertStringStartsWith("tenants/{$tenant->id}/logo/", $media->getPathRelativeToRoot());
    }

    public function test_un_fichier_de_marque_n_est_pas_servi_depuis_la_racine_web(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png'));

        $media = $tenant->fresh()->branding->getFirstMedia(BrandFile::Logo->value);

        $this->assertSame('tenant_media', $media->disk);
        $this->assertFileDoesNotExist(public_path($media->getPathRelativeToRoot()));
    }

    public function test_l_url_d_un_fichier_de_marque_est_signee_et_expirante(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png'));

        $url = $tenant->fresh()->branding->brandFileUrl(BrandFile::Logo);

        $this->assertNotNull($url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);
    }

    public function test_un_membre_sans_la_permission_de_marque_ne_depose_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->upload($tenant, $member, BrandFile::Logo, UploadedFile::fake()->image('logo.png'))
            ->assertForbidden();
    }

    public function test_un_locataire_tiers_recoit_404_sur_le_depot(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $stranger = User::factory()->withTwoFactor()->create();

        $this->upload($tenant, $stranger, BrandFile::Logo, UploadedFile::fake()->image('logo.png'))
            ->assertNotFound();
    }

    public function test_un_type_de_fichier_hors_catalogue_est_refuse(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->actingAs($owner)
            ->post(route('tenants.organisation.files.store', [$tenant, 'watermark']), [
                'file' => UploadedFile::fake()->image('logo.png'),
            ])
            ->assertNotFound();
    }

    public function test_le_proprietaire_supprime_un_fichier_de_marque(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png'));

        $this->actingAs($owner)
            ->delete(route('tenants.organisation.files.destroy', [$tenant, BrandFile::Logo->value]))
            ->assertRedirect();

        $this->assertNull($tenant->fresh()->branding->getFirstMedia(BrandFile::Logo->value));
    }

    public function test_un_membre_sans_la_permission_ne_supprime_rien(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Logo, UploadedFile::fake()->image('logo.png'));

        $member = User::factory()->withTwoFactor()->create();
        $this->joinWithPermissions($tenant, $member, [TenantPermission::TenantLegal]);

        $this->actingAs($member)
            ->delete(route('tenants.organisation.files.destroy', [$tenant, BrandFile::Logo->value]))
            ->assertForbidden();

        $this->assertNotNull($tenant->fresh()->branding->getFirstMedia(BrandFile::Logo->value));
    }

    public function test_le_depot_d_un_fichier_de_marque_est_journalise(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = $this->tenantOwnedBy($owner);

        $this->upload($tenant, $owner, BrandFile::Stamp, UploadedFile::fake()->image('cachet.png'));

        $activity = $tenant->asCurrent(fn () => Activity::where('description', 'organisation.brand_file_updated')
            ->latest('id')
            ->first());

        $this->assertNotNull($activity);
        $this->assertSame($owner->id, $activity->causer_id);
        $this->assertSame('stamp', $activity->properties['attributes']['collection']);
    }
}
