<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * L'abonnement d'une organisation (README section 3). Base centrale, avec `tenant_id` : comme les
 * autres tables centrales, elle est lue a travers les organisations.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property string|null $currency
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property string|null $payment_method_brand
 * @property string|null $payment_method_last4
 * @property Carbon|null $current_period_ends_at
 * @property Carbon|null $past_due_since
 * @property Carbon|null $overdue_reminder_sent_at
 * @property Carbon|null $suspended_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 */
#[Fillable([
    'tenant_id', 'plan_id', 'status', 'currency', 'stripe_customer_id', 'stripe_subscription_id',
    'payment_method_brand', 'payment_method_last4', 'current_period_ends_at', 'past_due_since',
    'overdue_reminder_sent_at', 'suspended_at', 'canceled_at',
])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use CentralConnection, HasFactory;

    /**
     * Get the organisation this subscription belongs to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the plan this subscription runs on.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the invoices issued for this subscription.
     *
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'current_period_ends_at' => 'datetime',
            'past_due_since' => 'datetime',
            'overdue_reminder_sent_at' => 'datetime',
            'suspended_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
