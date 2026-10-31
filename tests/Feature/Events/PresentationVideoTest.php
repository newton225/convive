<?php

namespace Tests\Feature\Events;

use App\Actions\Tenants\CreateTenant;
use App\Http\Requests\Events\SaveEventVideoRequest;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('event_videos');

        $this->owner = User::factory()->withTwoFactor()->create();
        $this->tenant = app(CreateTenant::class)->handle($this->owner, 'Association Convive');
        $this->event = $this->tenant->asCurrent(fn () => Event::factory()->open()->create());
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
        $this->actingAs($this->owner)->post($url, ['file' => $this->mp4(16)]);

        $count = $this->tenant->asCurrent(fn () => Event::find($this->event->id)->getMedia(Event::PresentationVideoCollection)->count());

        $this->assertSame(1, $count);
    }

    public function test_la_video_se_retire(): void
    {
        $this->actingAs($this->owner)
            ->post(route('tenants.events.video.store', [$this->tenant, $this->event]), ['file' => $this->mp4()]);

        $this->actingAs($this->owner)
            ->delete(route('tenants.events.video.destroy', [$this->tenant, $this->event]))
            ->assertRedirect();

        $this->assertFalse($this->hasVideo());
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
