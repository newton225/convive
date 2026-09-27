<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\HasTenants;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\ProductTour;
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
 * @property array{salt: string, hash: string, iterations: int}|null $scan_pin_verifier
 * @property array<int, string>|null $completed_tours
 * @property-read Tenant|null $currentTenant
 * @property-read Collection<int, Membership> $tenantMemberships
 * @property-read Collection<int, Tenant> $tenants
 */
#[Fillable(['name', 'email', 'phone', 'password', 'current_tenant_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'scan_pin_verifier'])]
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
            'scan_pin_verifier' => 'array',
            'completed_tours' => 'array',
        ];
    }

    /**
     * Remember that the member has finished or dismissed a guided tour.
     */
    public function completeTour(ProductTour $tour): void
    {
        $completed = $this->completed_tours ?? [];

        if (in_array($tour->value, $completed, true)) {
            return;
        }

        $this->forceFill(['completed_tours' => [...$completed, $tour->value]])->save();
    }

    /**
     * Iterations PBKDF2 de l'empreinte du code de scan : assez pour qu'une recherche exhaustive
     * des 10 000 codes coute un peu, assez peu pour qu'un telephone d'entree de gamme verifie le
     * code en moins d'une seconde.
     */
    public const ScanPinIterations = 210_000;

    /**
     * Replace the member's 4-digit scan code by its salted PBKDF2 verifier (SECURITY.md M8).
     */
    public function setScanPin(string $pin): void
    {
        $salt = random_bytes(16);

        $this->forceFill([
            'scan_pin_verifier' => [
                'salt' => base64_encode($salt),
                'hash' => base64_encode(hash_pbkdf2('sha256', $pin, $salt, self::ScanPinIterations, 32, true)),
                'iterations' => self::ScanPinIterations,
            ],
        ])->save();
    }
}
