<?php

namespace App\Models;

use App\Enums\ConsoleProfile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un membre de l'equipe editeur (README section 3 et ecran 34) : une adresse et son profil. Le
 * compte qui porte cette adresse, une fois verifiee, ouvre la console ; tant qu'aucun compte ne la
 * porte, c'est une invitation en attente.
 *
 * @property int $id
 * @property string $email
 * @property ConsoleProfile $profile
 * @property int|null $invited_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $invitedBy
 */
#[Fillable(['email', 'profile', 'invited_by_id'])]
class ConsoleOperator extends Model
{
    use CentralConnection;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the founder who invited this member.
     *
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profile' => ConsoleProfile::class,
        ];
    }
}
