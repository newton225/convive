<?php

namespace App\Models;

use App\Enums\BrandFile;
use App\Enums\LegalForm;
use App\Enums\TicketModel;
use Database\Factories\TenantBrandingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Identite legale et marque d'une organisation. Le cachet et la signature du responsable
 * s'apposent sur les billets, les recus et les exports PDF : ce ne sont pas des ornements.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $display_name
 * @property string|null $legal_name
 * @property LegalForm|null $legal_form
 * @property string|null $representative_name
 * @property string|null $registration_number
 * @property string|null $tax_number
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $primary_color
 * @property string|null $secondary_color
 * @property TicketModel $ticket_model
 * @property bool $ticket_element_logo
 * @property bool $ticket_element_stamp
 * @property bool $ticket_element_signature
 * @property bool $ticket_element_companions
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable([
    'display_name', 'legal_name', 'legal_form', 'representative_name',
    'registration_number', 'tax_number', 'address', 'city', 'country',
    'email', 'phone', 'primary_color', 'secondary_color',
    'ticket_model', 'ticket_element_logo', 'ticket_element_stamp',
    'ticket_element_signature', 'ticket_element_companions',
])]
class TenantBranding extends Model implements HasMedia
{
    /** @use HasFactory<TenantBrandingFactory> */
    use CentralConnection, HasFactory, InteractsWithMedia;

    /**
     * Couleurs de marque par defaut : bordeaux et or.
     */
    public const DefaultPrimaryColor = '#7b1e3a';

    public const DefaultSecondaryColor = '#c9a227';

    /**
     * Champs sans lesquels une organisation ne peut pas publier de lien public : ce sont ceux
     * qui figurent sur un recu et sur un billet. Tant qu'ils manquent, l'espace reste un
     * espace d'essai.
     *
     * @var array<int, string>
     */
    public const RequiredToPublish = [
        'display_name',
        'legal_name',
        'legal_form',
        'representative_name',
        'registration_number',
        'tax_number',
        'address',
        'city',
        'country',
        'phone',
    ];

    /**
     * Les quatre fichiers de marque, un seul exemplaire chacun.
     *
     * Le SVG est absent volontairement : il peut porter du script, et rien ne justifie ce
     * risque pour un logo. Voir SECURITY.md H1.
     */
    public function registerMediaCollections(): void
    {
        foreach (BrandFile::cases() as $file) {
            $this->addMediaCollection($file->value)
                ->singleFile()
                ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
        }
    }

    /**
     * Le fond du billet est recadre des le depot, aux proportions du haut du talon : voir
     * `BrandFile::TicketBackgroundConversion`. Synchrone, pour qu'il soit pret a l'apercu qui suit.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(BrandFile::TicketBackgroundConversion)
            ->performOnCollections(BrandFile::TicketBackground->value)
            ->nonQueued()
            // En dernier : `fit()` passe au pilote d'image et ne rend plus la conversion.
            ->fit(Fit::Crop, BrandFile::TicketBackgroundWidth, BrandFile::TicketBackgroundHeight);
    }

    /**
     * Get a signed, expiring URL for the ticket background, cropped for the ticket stub.
     */
    public function ticketBackgroundUrl(int $minutes = 30): ?string
    {
        $media = $this->getFirstMedia(BrandFile::TicketBackground->value);

        return $media instanceof Media && $media->hasGeneratedConversion(BrandFile::TicketBackgroundConversion)
            ? $media->getTemporaryUrl(now()->addMinutes($minutes), BrandFile::TicketBackgroundConversion)
            : null;
    }

    /**
     * Get a signed, expiring URL for the given brand file, or null when it is not set.
     *
     * Aucun fichier de marque n'est servi depuis la racine web : l'URL est signee et expire.
     */
    public function brandFileUrl(BrandFile $file, int $minutes = 30): ?string
    {
        $media = $this->getFirstMedia($file->value);

        return $media instanceof Media
            ? $media->getTemporaryUrl(now()->addMinutes($minutes))
            : null;
    }

    /**
     * Get the tenant this branding belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the brand colours, falling back to the product defaults.
     *
     * @return array{primary: string, secondary: string}
     */
    public function colors(): array
    {
        return [
            'primary' => $this->primary_color ?? self::DefaultPrimaryColor,
            'secondary' => $this->secondary_color ?? self::DefaultSecondaryColor,
        ];
    }

    /**
     * Get the legal identity fields still missing before a public link can be published.
     *
     * @return array<int, string>
     */
    public function missingBeforePublishing(): array
    {
        return array_values(array_filter(
            self::RequiredToPublish,
            fn (string $field) => blank($this->getAttribute($field)),
        ));
    }

    /**
     * Determine whether the legal identity is complete enough to publish a public link.
     */
    public function isReadyToPublish(): bool
    {
        return $this->missingBeforePublishing() === [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'legal_form' => LegalForm::class,
            'ticket_model' => TicketModel::class,
            'ticket_element_logo' => 'boolean',
            'ticket_element_stamp' => 'boolean',
            'ticket_element_signature' => 'boolean',
            'ticket_element_companions' => 'boolean',
        ];
    }
}
