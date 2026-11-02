<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Http\Requests\Events\SaveEventVideoRequest;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * La video courte de presentation d'un evenement (decision du 2026-10-31) : depot, retrait, verification du
 * vrai type et de la taille, et affichage sur le lien public par une URL signee.
 */
class PresentationVideoTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Tenant $tenant;

    private Event $event;

    private ?string $realRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('event_videos');

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
    }

    protected function tearDown(): void
    {
        if ($this->realRoot !== null) {
            File::deleteDirectory($this->realRoot);
        }

        parent::tearDown();
    }

    /**
     * Le vrai disque des videos sur un dossier temporaire, avec son service par morceaux : `Storage::fake()`
     * fabrique ses propres adresses temporaires (`?expiration=`, sans signature), il ne peut donc pas tester la
     * route signee du service de fichiers. Meme procede que `PaymentProofTest`.
     */
    private function useRealVideoDisk(): void
    {
        $this->realRoot = storage_path('framework/testing/event-videos-'.Str::random(8));

        config(['filesystems.disks.event_videos.root' => $this->realRoot]);
        Storage::forgetDisk('event_videos');
        AppServiceProvider::configureEventVideoDisk();
    }

    /**
     * Un petit fichier dont l'en-tete est celui d'un vrai mp4 (boite `ftyp`) : la validation lit le contenu.
     */
    private function mp4(int $kilobytes = 8): UploadedFile
    {
        $header = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

        // Le faux fichier de Laravel declare son type d'apres son nom (`application/mp4`) : on y pose celui
        // que PHP lit dans l'en-tete d'un vrai mp4, comme pour un depot reel.
        return UploadedFile::fake()
            ->createWithContent('presentation.mp4', $header.str_repeat("\0", $kilobytes * 1024))
            ->mimeType('video/mp4');
    }

    private function hasVideo(): bool
    {
        return $this->tenant->asCurrent(fn () => Event::find($this->event->id)->presentationVideoUrl() !== null);
    }

    public function test_un_organisateur_depose_une_video_mp4(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4()])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->hasVideo());
    }

    public function test_une_video_de_plus_de_cinq_mo_passe_la_limite_des_images_de_media_library(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4(6 * 1024)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue($this->hasVideo());
        $this->assertSame(1024 * 1024 * 5, config('media-library.max_file_size'));
    }

    public function test_un_fichier_qui_n_est_pas_une_video_est_refuse_meme_renomme_en_mp4(): void
    {
        // Un vrai fichier (le faux de Laravel rend le type declare, jamais le contenu) : du PHP annonce comme
        // mp4 par le client. Le serveur lit le contenu, pas ce que le client declare.
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, '<?php echo "pas une video";');

        try {
            $upload = new UploadedFile($path, 'video.mp4', 'video/mp4', null, true);
            $request = new SaveEventVideoRequest;

            $validator = Validator::make(['file' => $upload], $request->rules(), $request->messages());

            $this->assertTrue($validator->fails());
            $this->assertSame([__('events.video.errors.type')], $validator->errors()->get('file'));
        } finally {
            @unlink($path);
        }

        $this->assertFalse($this->hasVideo());
    }

    public function test_une_video_de_plus_de_vingt_cinq_mo_est_refusee(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4(Event::PresentationVideoMaxKilobytes + 1)])
            ->assertSessionHasErrors(['file' => __('events.video.errors.too_large')]);

        $this->assertFalse($this->hasVideo());
    }

    public function test_une_nouvelle_video_remplace_la_precedente(): void
    {
        $url = route('tenants.events.video.store', [$this->tenant, $this->event]);

        $this->actingAs($this->owner)->post($url, ['file' => $this->mp4(8)]);

        $first = Storage::disk('event_videos')->allFiles();
        $this->assertCount(1, $first);

        $this->actingAs($this->owner)->post($url, ['file' => $this->mp4(16)]);

        $count = $this->tenant->asCurrent(fn () => Event::find($this->event->id)->getMedia(Event::PresentationVideoCollection)->count());

        $this->assertSame(1, $count);

        // L'ancien fichier n'est plus sur le disque : il ne reste que le nouveau, plus lourd.
        $remaining = Storage::disk('event_videos')->allFiles();

        $this->assertCount(1, $remaining);
        $this->assertNotSame($first[0], $remaining[0]);
        $this->assertGreaterThan(16 * 1024, Storage::disk('event_videos')->size($remaining[0]));
        Storage::disk('event_videos')->assertMissing($first[0]);
    }

    public function test_la_video_se_retire(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4()]);

        $this->assertCount(1, Storage::disk('event_videos')->allFiles());

        $this->actingAs($this->owner)
            ->delete(route('tenants.events.video.destroy', [$this->tenant, $this->event]))
            ->assertRedirect();

        $this->assertFalse($this->hasVideo());
        // Le fichier a quitte le disque avec la video, pas seulement la ligne en base.
        $this->assertSame([], Storage::disk('event_videos')->allFiles());
    }

    public function test_la_video_se_lit_par_morceaux_pour_que_le_navigateur_puisse_la_demarrer(): void
    {
        $this->useRealVideoDisk();

        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4(64)]);

        $url = $this->tenant->asCurrent(fn () => Event::find($this->event->id)->presentationVideoUrl());

        $this->assertNotNull($url);

        $this->get($url, ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Length', '100')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_un_lien_de_video_modifie_est_refuse(): void
    {
        $this->useRealVideoDisk();

        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4()]);

        $url = $this->tenant->asCurrent(fn () => Event::find($this->event->id)->presentationVideoUrl());

        $this->get($url.'x')->assertForbidden();
    }

    public function test_un_tiers_ne_peut_ni_deposer_ni_retirer_une_video_d_une_autre_organisation(): void
    {
        $stranger = User::factory()->withTwoFactor()->create();
        app(CreateTenant::class)->handle($stranger, 'Autre Organisation');

        $this->actingAs($stranger)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4()])
            ->assertNotFound();

        $this->actingAs($stranger)
            ->delete(route('tenants.events.video.destroy', [$this->tenant, $this->event]))
            ->assertNotFound();

        $this->assertFalse($this->hasVideo());
    }
}
