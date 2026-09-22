<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Le canal choisi par un utilisateur pour un type d'alerte (README section 5). Base centrale :
 * c'est un reglage de la personne, il la suit dans chacune de ses organisations.
 *
 * @property int $id
 * @property int $user_id
 * @property NotificationType $type
 * @property NotificationChannel $channel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'type', 'channel'])]
class NotificationPreference extends Model
{
    use CentralConnection;

    /**
     * Get the user this choice belongs to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'channel' => NotificationChannel::class,
        ];
    }
}
