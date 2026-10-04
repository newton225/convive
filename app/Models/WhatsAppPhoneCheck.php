<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un code de verification du telephone par WhatsApp (voir `PhoneVerification`) : l'invite l'envoie
 * depuis son WhatsApp au numero de Convive. Central, car le message arrive a une adresse unique
 * pour toute la plateforme ; l'inscription, elle, vit dans la base de l'organisation.
 *
 * @property int $id
 * @property string $code
 * @property int $tenant_id
 * @property int $registration_id
 * @property string $phone
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 */
#[Fillable(['code', 'tenant_id', 'registration_id', 'phone', 'expires_at', 'verified_at'])]
class WhatsAppPhoneCheck extends Model
{
    use CentralConnection;

    protected $table = 'whatsapp_phone_checks';

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
