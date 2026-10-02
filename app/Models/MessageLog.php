<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un envoi releve pour la console (README section 3) : le canal, le type de message, le
 * destinataire masque. Jamais le contenu. Ecriture seule, purgee au bout de 30 jours.
 *
 * @property int $id
 * @property string $channel
 * @property string $type
 * @property int|null $tenant_id
 * @property string|null $recipient
 * @property bool $simulated
 * @property CarbonInterface $created_at
 */
#[Fillable(['channel', 'type', 'tenant_id', 'recipient', 'simulated', 'created_at'])]
class MessageLog extends Model
{
    use CentralConnection;

    public const RetentionDays = 30;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'simulated' => 'boolean'];
    }
}
