<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une entree du journal central de la console (README section 3 et ecran 33). Ecriture seule :
 * l'application ne modifie ni ne supprime une entree, hors purge a 24 mois. Le nom de l'acteur et
 * celui de l'organisation sont recopies : l'entree reste lisible apres leur suppression.
 *
 * @property int $id
 * @property string $type
 * @property int|null $actor_id
 * @property string|null $actor_name
 * @property int|null $tenant_id
 * @property string|null $organisation
 * @property array<string, mixed>|null $properties
 * @property string|null $ip
 * @property Carbon $created_at
 */
#[Fillable(['type', 'actor_id', 'actor_name', 'tenant_id', 'organisation', 'properties', 'ip', 'created_at'])]
class ConsoleActionLog extends Model
{
    use CentralConnection;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
