<?php

namespace App\Models;

use App\Enums\RegistrationEventActor;
use App\Enums\RegistrationEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Une ligne de l'historique d'un dossier. Vit dans la base du locataire. Ecriture seule : jamais mise
 * a jour (pas de `updated_at`), supprimee avec son dossier, adresse IP effacee apres douze mois.
 *
 * @property int $id
 * @property int $event_id
 * @property int|null $registration_id
 * @property int|null $waitlist_entry_id
 * @property RegistrationEventType $type
 * @property RegistrationEventActor $actor
 * @property string|null $ip_address
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read Registration|null $registration
 */
#[Fillable(['event_id', 'registration_id', 'waitlist_entry_id', 'type', 'actor', 'ip_address', 'properties'])]
class RegistrationEvent extends Model
{
    public const UPDATED_AT = null;

    /** Duree de garde de l'adresse IP (mentionnee dans la politique de confidentialite). */
    public const IpRetentionMonths = 12;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => RegistrationEventType::class,
            'actor' => RegistrationEventActor::class,
            'properties' => 'array',
        ];
    }
}
