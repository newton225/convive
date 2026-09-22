<?php

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantPermission;
use App\Models\Subscription;
use App\Notifications\Billing\PaymentOverdue;
use App\Notifications\Billing\SubscriptionSuspended;
use Illuminate\Support\Facades\Notification;

/**
 * La relance et la suspension des impayes (README section 3, « Facturation »), jouees chaque jour
 * par la tache planifiee : relance a J+3 d'impaye, suspension de l'espace a J+10.
 *
 * Idempotente : la relance ne part qu'une fois (`overdue_reminder_sent_at`) et seule une
 * organisation encore `PastDue` est suspendue. Rejouer la tache toutes les heures ne renvoie rien
 * et ne suspend personne deux fois.
 */
class ProcessOverdueSubscriptions
{
    public const ReminderAfterDays = 3;

    public const SuspensionAfterDays = 10;

    /**
     * @return array{reminded: int, suspended: int}
     */
    public function handle(): array
    {
        $suspended = 0;
        $reminded = 0;

        Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->whereNotNull('past_due_since')
            ->with('tenant')
            ->each(function (Subscription $subscription) use (&$suspended, &$reminded) {
                $daysOverdue = (int) $subscription->past_due_since->diffInDays(now());

                if ($daysOverdue >= self::SuspensionAfterDays) {
                    $subscription->update(['status' => SubscriptionStatus::Suspended, 'suspended_at' => now()]);
                    $this->notify($subscription, new SubscriptionSuspended($subscription->tenant->name));
                    $suspended++;

                    return;
                }

                if ($daysOverdue >= self::ReminderAfterDays && $subscription->overdue_reminder_sent_at === null) {
                    $subscription->update(['overdue_reminder_sent_at' => now()]);
                    $this->notify($subscription, new PaymentOverdue(
                        $subscription->tenant->name,
                        self::SuspensionAfterDays - $daysOverdue,
                    ));
                    $reminded++;
                }
            });

        return ['reminded' => $reminded, 'suspended' => $suspended];
    }

    private function notify(Subscription $subscription, PaymentOverdue|SubscriptionSuspended $notification): void
    {
        Notification::send(
            $subscription->tenant->membersWithPermission(TenantPermission::BillingManage),
            $notification,
        );
    }
}
