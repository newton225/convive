<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Une facture d'abonnement (README section 3). Base centrale, avec `tenant_id`. Montant en francs
 * CFA sans decimale.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $subscription_id
 * @property string $number
 * @property int $amount
 * @property string $currency
 * @property InvoiceStatus $status
 * @property string|null $stripe_invoice_id
 * @property string|null $hosted_invoice_url
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon $issued_at
 * @property Carbon|null $paid_at
 * @property-read Tenant $tenant
 * @property-read Subscription|null $subscription
 */
#[Fillable([
    'tenant_id', 'subscription_id', 'number', 'amount', 'currency', 'status',
    'stripe_invoice_id', 'hosted_invoice_url', 'period_start', 'period_end', 'issued_at', 'paid_at',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use CentralConnection, HasFactory;

    /**
     * Get the organisation this invoice was issued to.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the subscription this invoice belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => InvoiceStatus::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
