<?php

namespace App\Actions\Console;

use App\Actions\Billing\ProcessOverdueSubscriptions;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Models\ConsoleActionLog;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\Billing\PaymentOverdue;
use App\Support\Console\ConsoleJournal;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Relancer a la main une organisation en impaye (README ecran 29) : le meme courriel que la
 * relance automatique de J+3, aux membres qui gerent l'abonnement, avec le nombre de jours avant
 * la suspension. Une relance par jour au plus : la console ne doit pas servir a harceler.
 */
class SendPaymentReminder
{
    /**
     * @throws ValidationException
     */
    public function handle(Tenant $tenant, User $actor): void
    {
        $subscription = $tenant->subscription;

        if ($subscription?->status !== SubscriptionStatus::PastDue || $subscription->past_due_since === null) {
            throw ValidationException::withMessages(['organisation' => __('console.recovery.errors.not_past_due')]);
        }

        $remindedToday = ConsoleActionLog::where('type', 'payment_reminder_sent')
            ->where('tenant_id', $tenant->id)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($remindedToday) {
            throw ValidationException::withMessages(['organisation' => __('console.recovery.errors.already_reminded')]);
        }

        $daysLeft = max(0, ProcessOverdueSubscriptions::SuspensionAfterDays - (int) $subscription->past_due_since->diffInDays(now()));

        Notification::send(
            $tenant->membersWithPermission(TenantPermission::BillingManage),
            new PaymentOverdue($tenant->name, $daysLeft),
        );

        ConsoleJournal::record('payment_reminder_sent', $actor, $tenant, ['days_before_suspension' => $daysLeft]);
    }
}
