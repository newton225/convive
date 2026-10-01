<?php

namespace App\Actions\Tenants;

use App\Enums\BrandFile;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SaveTenantBrandFile
{
    /**
     * Store a brand file, replacing whatever was there before. `$crop` is the area the operator
     * chose, in pixels of the upright image, already checked by `SaveBrandFileRequest`.
     *
     * @param  array{x: int, y: int, width: int, height: int}|null  $crop
     */
    public function store(Tenant $tenant, BrandFile $file, UploadedFile $upload, ?array $crop = null): Media
    {
        $branding = $tenant->brandingOrCreate();

        $media = $branding
            ->addMedia($this->reencode($upload, $crop))
            ->usingFileName(Str::uuid()->toString().'.'.$this->extension($upload))
            ->usingName($file->value)
            ->toMediaCollection($file->value);

        activity()
            ->performedOn($branding)
            ->event('updated')
            ->withProperties([
                'attributes' => [
                    'collection' => $file->value,
                    'file_name' => $media->file_name,
                    'size' => $media->size,
                ],
                'tenant_id' => $tenant->id,
            ])
            ->log('organisation.brand_file_updated');

        return $media;
    }

    /**
     * Remove the given brand file.
     */
    public function delete(Tenant $tenant, BrandFile $file): void
    {
        $branding = $tenant->brandingOrCreate();

        $branding->clearMediaCollection($file->value);

        activity()
            ->performedOn($branding)
            ->event('deleted')
            ->withProperties([
                'old' => ['collection' => $file->value],
                'tenant_id' => $tenant->id,
            ])
            ->log('organisation.brand_file_deleted');
    }

    /**
     * Re-encode the upload to a temporary file, dropping every metadata block.
     *
     * Le reencodage n'est pas une optimisation : une photo de cachet porte souvent la position
     * GPS et le modele d'appareil de qui l'a prise. Le fichier stocke doit etre une image et
     * rien d'autre. Voir CLAUDE.md, « Fichiers deposes ».
     */
    /**
     * @param  array{x: int, y: int, width: int, height: int}|null  $crop
     */
    private function reencode(UploadedFile $upload, ?array $crop): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'brand').'.'.$this->extension($upload);

        // Redressee d'apres son EXIF avant que le reencodage ne l'efface : sinon une photo prise
        // au telephone, droite a l'ecran, serait stockee couchee, et la zone choisie a l'ecran
        // ne correspondrait plus aux pixels decoupes.
        $image = Image::load($upload->getRealPath())->orientation();

        if ($crop !== null) {
            $image->manualCrop($crop['width'], $crop['height'], $crop['x'], $crop['y']);
        }

        $image->save($destination);

        return $destination;
    }

    private function extension(UploadedFile $upload): string
    {
        return match ($upload->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }
}
