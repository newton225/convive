<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Le resultat de la derniere verification d'un journal d'audit (SECURITY.md M6) : le journal
 * central ou celui d'une organisation. `broken_entry_id` designe la premiere entree dont
 * l'empreinte ne suit plus la precedente : une entree modifiee ou supprimee hors de la purge tracee.
 *
 * @property int $id
 * @property string $scope
 * @property int|null $tenant_id
 * @property string|null $organisation
 * @property CarbonInterface $checked_at
 * @property int|null $broken_entry_id
 */
#[Fillable(['scope', 'tenant_id', 'organisation', 'checked_at', 'broken_entry_id'])]
class AuditChainCheck extends Model
{
    use CentralConnection;

    public const Central = 'central';

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    public function isBroken(): bool
    {
        return $this->broken_entry_id !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }
}
