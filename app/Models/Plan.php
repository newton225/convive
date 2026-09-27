<?php

namespace App\Models;

use App\Enums\BillingCurrency;
use App\Enums\PlanCode;
use App\Enums\PlanFeature;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Un plan (README section 3). Base centrale : commun a toutes les organisations. Un plafond `null`
 * veut dire illimite.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $monthly_price
 * @property int|null $monthly_price_eur
 * @property int|null $monthly_price_usd
 * @property int|null $max_active_events
 * @property int|null $max_registrations
 * @property int|null $max_members
 * @property int|null $max_messages_per_month
 * @property bool $has_reconciliation
 * @property bool $has_reports
 * @property bool $has_custom_domain
 * @property bool $has_sso
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'code', 'name', 'monthly_price', 'monthly_price_eur', 'monthly_price_usd', 'max_active_events', 'max_registrations', 'max_members', 'max_messages_per_month',
    'has_reconciliation', 'has_reports', 'has_custom_domain', 'has_sso', 'position',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use CentralConnection, HasFactory;

    /**
     * Get the row of the given plan, creating it from its default definition on first use.
     *
     * `firstOrCreate` et non `updateOrCreate` : une valeur ajustee en base par l'exploitant n'est
     * jamais ecrasee par la valeur d'origine du code.
     */
    public static function ensure(PlanCode $code): self
    {
        return self::firstOrCreate(['code' => $code->value], $code->definition());
    }

    /**
     * Get the monthly price in the given currency, in its minor unit, or null when the plan is not
     * offered in it (or is negotiated on quote). Zero means free.
     */
    public function priceIn(BillingCurrency $currency): ?int
    {
        return $this->getAttribute($currency->planColumn());
    }

    /**
     * Determine whether the plan opens the given feature.
     */
    public function allows(PlanFeature $feature): bool
    {
        return (bool) $this->getAttribute($feature->column());
    }

    /**
     * Get the subscriptions on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monthly_price' => 'integer',
            'monthly_price_eur' => 'integer',
            'monthly_price_usd' => 'integer',
            'max_active_events' => 'integer',
            'max_registrations' => 'integer',
            'max_members' => 'integer',
            'max_messages_per_month' => 'integer',
            'has_reconciliation' => 'boolean',
            'has_reports' => 'boolean',
            'has_custom_domain' => 'boolean',
            'has_sso' => 'boolean',
            'position' => 'integer',
        ];
    }
}
