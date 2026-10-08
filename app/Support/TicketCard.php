<?php

namespace App\Support;

use App\Enums\BrandFile;
use App\Models\Event;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Models\Ticket;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Les donnees d'un billet au format talon (README ecran 7 et 15), une seule source pour toutes ses
 * surfaces : la page de l'invite, le lien individuel d'un accompagnateur et le PDF. L'invite
 * principal et chacun de ses accompagnateurs recoivent ainsi exactement le meme billet, rendu par
 * `resources/js/components/ticket-template/branded-ticket.tsx` et, pour le PDF, par
 * `resources/views/pdf/tickets.blade.php`, qui doivent evoluer ensemble.
 */
class TicketCard
{
    /**
     * Get what the ticket says about the person it admits.
     *
     * @return array{holder: array{name: string, unit: string}, qrImage: string, tableNumber: int|null, seatsAtTables: bool, priceCategory: string|null, seats: int, companions: array<int, array{name: string, unit: string, priceCategory: string|null}>, host: array{name: string, unit: string, reference: string|null}|null}
     */
    public static function for(Ticket $ticket, TenantBranding $branding): array
    {
        return [
            'holder' => ['name' => $ticket->holderName(), 'unit' => $ticket->holderUnitName()],
            'qrImage' => TicketQrCode::dataUri($ticket->signedToken()),
            'tableNumber' => $ticket->registration->tableAssignment?->seatingTable->number,
            // Sans table, le billet ne parle pas de table ; le nom du tarif dit a quoi il donne droit.
            'seatsAtTables' => $ticket->registration->event->seatsAtTables(),
            'priceCategory' => $ticket->holderPriceCategoryName(),
            // Le billet principal compte tout le groupe ; celui d'un accompagnateur, sa seule place.
            'seats' => $ticket->isCompanion() ? 1 : $ticket->registration->party_size,
            // La liste suit le gabarit ; la personne qui invite, jamais : c'est ce qui rattache un
            // accompagnateur a son groupe a l'entree.
            'companions' => $ticket->registration->event->ticketTemplate($branding)['elements']['companions']
                ? $ticket->companionsOfHolder()
                : [],
            'host' => $ticket->host(),
        ];
    }

    /**
     * @return array{name: string, startsAt: string|null, venue: string|null}
     */
    public static function event(Event $event): array
    {
        return [
            'name' => $event->name,
            'startsAt' => $event->starts_at?->toISOString(),
            'venue' => $event->venue,
        ];
    }

    /**
     * Get the organisation's ticket template for a web page : model, elements, brand, with brand
     * files as signed expiring URLs.
     *
     * @return array{model: string, elements: array{logo: bool, stamp: bool, signature: bool, companions: bool}, brand: array{displayName: string, colors: array{primary: string, secondary: string}, logoUrl: string|null, stampUrl: string|null, signatureUrl: string|null, backgroundUrl: string|null, bodyBackgroundUrl: string|null}}
     */
    public static function design(Tenant $tenant, Event $event): array
    {
        $branding = $tenant->brandingOrCreate();

        return self::designWith(
            $tenant,
            $branding,
            $event,
            fn (BrandFile $file) => $branding->brandFileUrl($file),
            fn (Media $media, string $conversion) => $media->getTemporaryUrl(now()->addMinutes(30), $conversion),
        );
    }

    /**
     * The same template for the PDF, brand files embedded as `data:` URIs (see `PdfLetterhead`).
     *
     * @return array{model: string, elements: array{logo: bool, stamp: bool, signature: bool, companions: bool}, brand: array{displayName: string, colors: array{primary: string, secondary: string}, logoUrl: string|null, stampUrl: string|null, signatureUrl: string|null, backgroundUrl: string|null, bodyBackgroundUrl: string|null}, tints: array{stub: string, host: string, badge: string}}
     */
    public static function pdfDesign(Tenant $tenant, Event $event): array
    {
        $branding = $tenant->brandingOrCreate();

        $design = self::designWith(
            $tenant,
            $branding,
            $event,
            fn (BrandFile $file) => PdfLetterhead::brandFileDataUri($branding, $file),
            fn (Media $media, string $conversion) => PdfLetterhead::mediaDataUri($media, $conversion),
        );

        // Les teintes que le composant obtient par opacite (`bg-[color:var(--brand-primary)]/8`),
        // precalculees sur fond blanc : le moteur PDF ne compose pas la transparence de facon
        // fiable.
        $primary = $design['brand']['colors']['primary'];

        return [
            ...$design,
            'tints' => [
                'stub' => self::tint($primary, 0.08),
                'host' => self::tint($primary, 0.06),
                'badge' => self::tint($primary, 0.10),
            ],
        ];
    }

    /**
     * Mix a `#rrggbb` color with white, `$ratio` being the share of the color.
     */
    private static function tint(string $hex, float $ratio): string
    {
        $channels = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return '#'.implode('', array_map(
            fn ($channel) => sprintf('%02x', (int) round(255 - (255 - (int) $channel) * $ratio)),
            is_array($channels) ? $channels : [255, 255, 255],
        ));
    }

    /**
     * @param  callable(BrandFile): (string|null)  $file
     * @param  callable(Media, string): (string|null)  $background  the address of a cropped ticket background
     * @return array{model: string, elements: array{logo: bool, stamp: bool, signature: bool, companions: bool}, brand: array{displayName: string, colors: array{primary: string, secondary: string}, logoUrl: string|null, stampUrl: string|null, signatureUrl: string|null, backgroundUrl: string|null, bodyBackgroundUrl: string|null}}
     */
    private static function designWith(Tenant $tenant, TenantBranding $branding, Event $event, callable $file, callable $background): array
    {
        // Le gabarit de l'evenement s'il en a active un, celui de l'organisation sinon.
        $template = $event->ticketTemplate($branding);
        $elements = $template['elements'];

        $backgroundOf = function (BrandFile $backgroundFile) use ($event, $branding, $background): ?string {
            $media = $event->ticketBackgroundMedia($backgroundFile, $branding);
            $conversion = $backgroundFile->conversion();

            return $media !== null && $conversion !== null ? $background($media, $conversion) : null;
        };

        return [
            'model' => $template['model']->value,
            'elements' => $elements,
            'brand' => [
                'displayName' => $branding->display_name ?? $tenant->name,
                // Couleurs de l'evenement si elles sont posees, de l'organisation sinon.
                'colors' => $event->colors(),
                'logoUrl' => $elements['logo'] ? $file(BrandFile::Logo) : null,
                'stampUrl' => $elements['stamp'] ? $file(BrandFile::Stamp) : null,
                'signatureUrl' => $elements['signature'] ? $file(BrandFile::Signature) : null,
                'backgroundUrl' => $backgroundOf(BrandFile::TicketBackground),
                'bodyBackgroundUrl' => $backgroundOf(BrandFile::TicketBodyBackground),
            ],
        ];
    }
}
