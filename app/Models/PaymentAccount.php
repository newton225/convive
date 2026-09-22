<?php

namespace App\Models;

use App\Enums\PaymentChannel;
use Carbon\CarbonImmutable;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un compte sur lequel les invites versent. C'est la surface la plus attaquee du produit :
 * changer discretement ce numero detourne tout l'argent d'un evenement sans laisser de trace
 * cote invite, qui deposera des preuves parfaitement authentiques. Voir SECURITY.md C1.
 *
 * Vit dans la base du locataire (voir CLAUDE.md, « Multi-locataire ») : aucune colonne
 * `tenant_id`. `requester()` reste une relation Eloquent normale malgre la base separee : elle
 * interroge `User` sur SA propre connexion (centrale), independamment de celle-ci.
 *
 * Les colonnes vivantes sont celles que voit l'invite. Les colonnes `pending_*` portent une
 * modification demandee et pas encore active : l'ancien numero reste affiche pendant le delai.
 *
 * @property int $id
 * @property string $label
 * @property PaymentChannel|null $channel
 * @property string|null $account_number
 * @property string|null $holder_name
 * @property string|null $instructions
 * @property PaymentChannel|null $pending_channel
 * @property string|null $pending_account_number
 * @property string|null $pending_holder_name
 * @property CarbonImmutable|null $pending_activates_at
 * @property int|null $pending_requested_by
 * @property int|null $pending_approved_by
 * @property CarbonImmutable|null $last_changed_at
 * @property bool $is_active
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User|null $requester
 */
#[Fillable(['label', 'instructions', 'is_active', 'position'])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /**
     * Voir `Event::$dateFormat` : meme raison, meme valeur constante pour toutes les
     * connexions SQLite de l'application.
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Delai avant qu'un numero modifie apparaisse sur un lien public.
     *
     * Vingt-quatre heures : assez pour qu'un Proprietaire prevenu par email s'en apercoive,
     * assez court pour ne pas paralyser une correction legitime. Un second Proprietaire peut
     * le lever, ce qui couvre l'urgence de la veille d'un evenement.
     */
    public const ActivationDelayHours = 24;

    /**
     * Duree pendant laquelle un changement reste signale sur le tableau de bord.
     */
    public const ChangeNoticeDays = 7;

    /**
     * Get the member who requested the pending change.
     *
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pending_requested_by');
    }

    /**
     * Determine whether a change is waiting to take effect.
     */
    public function hasPendingChange(): bool
    {
        return $this->pending_activates_at !== null;
    }

    /**
     * Determine whether the pending change is now due.
     */
    public function pendingChangeIsDue(): bool
    {
        return $this->hasPendingChange()
            && $this->pending_activates_at->isPast();
    }

    /**
     * Determine whether the account is shown to guests.
     *
     * Un compte tout juste cree n'a pas encore de valeurs vivantes : il n'apparait nulle part
     * tant que le delai n'est pas ecoule. Sans cette regle, il suffirait d'ajouter un compte
     * plutot que d'en modifier un pour contourner le delai.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->is_active && $this->account_number !== null;
    }

    /**
     * Determine whether the last change is recent enough to be flagged on the dashboard.
     */
    public function changedRecently(): bool
    {
        return $this->last_changed_at !== null
            && $this->last_changed_at->greaterThan(now()->subDays(self::ChangeNoticeDays));
    }

    /**
     * Scope the query to the accounts shown to guests.
     *
     * @param  Builder<PaymentAccount>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('account_number');
    }

    /**
     * Scope the query to the display order chosen by the operator.
     *
     * @param  Builder<PaymentAccount>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('label');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'pending_channel' => PaymentChannel::class,
            'pending_activates_at' => 'datetime',
            'last_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
