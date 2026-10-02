<?php

namespace Tests\Feature;

use App\Actions\Tenants\CreateTenant;
use App\Enums\BrandFile;
use App\Models\User;
use App\Rules\ImagePixelBudget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Le plafond de pixels d'une image deposee (SECURITY.md H1, « limites de pixels imposees ») : un
 * fichier leger peut declarer des dimensions qui, une fois decodees pour le reencodage, depassent
 * la memoire du serveur et font tomber la requete. L'image est refusee avant d'etre decodee, avec
 * un message qui dit quoi envoyer a la place.
 */
class ImagePixelBudgetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un PNG reduit a son en-tete : il declare ses dimensions sans porter un seul pixel, comme le
     * ferait un fichier forge.
     */
    private function png(int $width, int $height): UploadedFile
    {
        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $content = "\x89PNG\r\n\x1a\n"
            .pack('N', 13).'IHDR'.$header.pack('N', crc32('IHDR'.$header))
            .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        return UploadedFile::fake()->createWithContent('image.png', $content);
    }

    private function passes(UploadedFile $file): bool
    {
        return Validator::make(['file' => $file], ['file' => [new ImagePixelBudget]])->passes();
    }

    public function test_une_photo_de_telephone_ordinaire_est_acceptee(): void
    {
        // 12 megapixels : la photo par defaut de la plupart des telephones.
        $this->assertTrue($this->passes($this->png(4032, 3024)));
    }

    public function test_une_image_dont_les_dimensions_epuiseraient_la_memoire_est_refusee(): void
    {
        // 49 megapixels dans un fichier de 57 octets : chaque cote reste sous 8000 pixels.
        $this->assertFalse($this->passes($this->png(7000, 7000)));
    }

    public function test_le_refus_dit_quoi_envoyer_a_la_place(): void
    {
        $validator = Validator::make(['file' => $this->png(7000, 7000)], ['file' => [new ImagePixelBudget]]);

        $this->assertSame(__('common.errors.image_too_large'), $validator->errors()->first('file'));
    }

    public function test_un_fichier_de_marque_trop_grand_est_refuse_a_la_validation(): void
    {
        $owner = User::factory()->withTwoFactor()->create();
        $tenant = app(CreateTenant::class)->handle($owner, 'Association Convive');

        $this->actingAs($owner)
            ->post(route('tenants.organisation.files.store', [$tenant, BrandFile::Logo->value]), ['file' => $this->png(7000, 7000)])
            ->assertSessionHasErrors('file');

        $this->assertNull($tenant->fresh()->branding?->getFirstMedia(BrandFile::Logo->value));
    }
}
