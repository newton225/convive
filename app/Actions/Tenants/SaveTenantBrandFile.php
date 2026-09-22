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
     * Store a brand file, replacing whatever was there before.
     */
    public function store(Tenant $tenant, BrandFile $file, UploadedFile $upload): Media
    {
        $branding = $tenant->brandingOrCreate();

        $media = $branding
            ->addMedia($this->reencode($upload))
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
    private function reencode(UploadedFile $upload): string
    {
        $destination = tempnam(sys_get_temp_dir(), 'brand').'.'.$this->extension($upload);

        Image::load($upload->getRealPath())->save($destination);

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
