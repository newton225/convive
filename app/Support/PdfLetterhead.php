<?php

namespace App\Support;

use App\Enums\BrandFile;
use App\Models\Tenant;
use App\Models\TenantBranding;
use finfo;
use Illuminate\Support\Facades\Storage;
use Locale;
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
            self::countryName($branding?->country),
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

    /**
     * Get one brand file embedded as a `data:` URI, or null when the organisation has none.
     * `$conversion` names a generated version (see `BrandFile::TicketBackgroundConversion`).
     */
    public static function brandFileDataUri(TenantBranding $branding, BrandFile $file, string $conversion = ''): ?string
    {
        $media = $branding->getFirstMedia($file->value);

        if ($conversion !== '' && $media instanceof Media && ! $media->hasGeneratedConversion($conversion)) {
            return null;
        }

        return self::dataUri($media, $conversion);
    }

    /**
     * Get a stored image (or one of its generated versions) embedded as a `data:` URI.
     */
    public static function mediaDataUri(Media $media, string $conversion = ''): ?string
    {
        return self::dataUri($media, $conversion);
    }

    /**
     * Le nom du pays dans la langue du document (`CI` : « Côte d'Ivoire »). Il vient de l'extension
     * `intl` de PHP ; sans elle, le code reste lisible plutot que de ne rien imprimer.
     */
    private static function countryName(?string $code): ?string
    {
        if ($code === null || $code === '' || ! class_exists(Locale::class)) {
            return $code;
        }

        $name = Locale::getDisplayRegion('-'.$code, app()->getLocale());

        return $name !== '' && $name !== $code ? $name : $code;
    }

    private static function dataUri(?Media $media, string $conversion = ''): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $content = Storage::disk($media->disk)->get($media->getPathRelativeToRoot($conversion));

        if ($content === null) {
            return null;
        }

        // Une conversion garde le format de l'original par defaut, mais son type se lit sur
        // les octets plutot que de le supposer.
        $mime = $conversion === '' ? $media->mime_type : ((new finfo(FILEINFO_MIME_TYPE))->buffer($content) ?: $media->mime_type);

        return "data:{$mime};base64,".base64_encode($content);
    }
}
