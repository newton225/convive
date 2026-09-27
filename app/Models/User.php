<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTenants;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_tenant_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $currentTenant
 * @property-read Collection<int, Membership> $tenantMemberships
 * @property-read Collection<int, Tenant> $tenants
 */
#[Fillable(['name', 'email', 'phone', 'password', 'current_tenant_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    // Toujours la base centrale, meme quand une tenancy est active : un utilisateur
    // appartient a plusieurs organisations, il doit rester lisible independamment de celle
    // qui est en cours (voir CLAUDE.md, « Multi-locataire »).
    /** @use HasFactory<UserFactory> */
    use CentralConnection, HasFactory, HasTenants, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the user's alerts, newest first.
     *
     * Surcharge de `HasDatabaseNotifications::notifications()` : le modele du paquet suivrait la
     * connexion par defaut du moment, donc celle d'un locataire quand une action metier envoie
     * l'alerte. `App\Models\DatabaseNotification` reste sur la base centrale.
     *
     * @return MorphMany<DatabaseNotification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(DatabaseNotification::class, 'notifiable')->latest();
    }

    /**
     * Get the channel choices this user made, one per alert type at most.
     *
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Get the channel this user wants for the given alert type : their own choice, or the default.
     */
    public function notificationChannelFor(NotificationType $type): NotificationChannel
    {
        return $this->notificationPreferences()->where('type', $type->value)->first()->channel
            ?? NotificationChannel::default();
    }

    /**
     * Route a WhatsApp notification, canal enregistre par `AppServiceProvider`
     * (`App\Notifications\Channels\WhatsAppChannel`). `null` quand le membre n'a jamais
     * renseigne de numero (README ecran 25, facultatif) : la notification saute simplement ce
     * canal, comme `route('mail', null)` le fait deja pour une inscription sans email.
     */
    public function routeNotificationForWhatsapp(?Notification $notification = null): ?string
    {
        return $this->phone;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
