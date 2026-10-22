<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un fait de securite montre a l'equipe Convive (README section 3) : une limite de debit atteinte,
 * une connexion verrouillee apres trop d'echecs, et la vie des acces (connexions reussies ou
 * echouees, deconnexions, double authentification, mot de passe, sessions fermees). Ecriture seule,
 * purgee au bout de 90 jours.
 *
 * @property int $id
 * @property string $type
 * @property string|null $subject
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string|null $ip
 * @property CarbonInterface $created_at
 */
#[Fillable(['type', 'subject', 'tenant_id', 'user_id', 'ip', 'created_at'])]
class SecurityEvent extends Model
{
    use CentralConnection;

    public const RateLimited = 'rate_limited';

    public const LoginLockout = 'login_lockout';

    public const LoginSucceeded = 'login_succeeded';

    public const LoginFailed = 'login_failed';

    public const Logout = 'logout';

    public const TwoFactorEnabled = 'two_factor_enabled';

    public const TwoFactorDisabled = 'two_factor_disabled';

    public const TwoFactorFailed = 'two_factor_failed';

    public const PasswordChanged = 'password_changed';

    public const SessionsEnded = 'sessions_ended';

    public const RetentionDays = 90;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
