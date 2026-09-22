<?php

namespace App\Support;

use App\Enums\BrandFile;
use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * L'en-tete et le pied des documents PDF d'une organisation (exports, listes de controle,
 * rapports) : nom, identite legale, cachet et signature.
 *
 * Cachet et signature sont embarques en `data:` URI plutot que servis par l'URL signee de
 * `TenantBranding::brandFileUrl()` : le moteur PDF n'a pas a refaire une requete HTTP vers
 * l'application pour les recuperer, et un document produit reste lisible sans reseau. Ce sont des
 * images reencodees a l'envoi (voir `SaveTenantBrandFile`), jamais le fichier d'origine.
 */
class PdfLetterhead
{
    /**
     * Get the letterhead data passed to every PDF template.
     *
     * @return array{name: string, legalLine: string|null, contactLine: string|null, primaryColor: string, stamp: string|null, signature: string|null, representative: string|null}
     */
    public static function for(Tenant $tenant): array
    {
        $branding = $tenant->branding;

        $legalLine = collect([
            $branding?->legal_name,
            $branding?->legal_form?->label(),
            $branding?->registration_number ? __('reports.pdf.registration_number', ['number' => $branding->registration_number]) : null,
            $branding?->tax_number ? __('reports.pdf.tax_number', ['number' => $branding->tax_number]) : null,
        ])->filter()->implode(' · ');

        $contactLine = collect([
            $branding?->address,
            $branding?->city,
            $branding?->country,
            $branding?->phone,
        ])->filter()->implode(' · ');

        return [
            'name' => $branding?->display_name ?: $tenant->name,
            'legalLine' => $legalLine === '' ? null : $legalLine,
            'contactLine' => $contactLine === '' ? null : $contactLine,
            'primaryColor' => $branding?->colors()['primary'] ?? '#7b1e3a',
            'stamp' => $branding === null ? null : self::dataUri($branding->getFirstMedia(BrandFile::Stamp->value)),
            'signature' => $branding === null ? null : self::dataUri($branding->getFirstMedia(BrandFile::Signature->value)),
            'representative' => $branding?->representative_name,
        ];
    }

    private static function dataUri(?Media $media): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $content = Storage::disk($media->disk)->get($media->getPathRelativeToRoot());

        return $content === null ? null : "data:{$media->mime_type};base64,".base64_encode($content);
    }
}
