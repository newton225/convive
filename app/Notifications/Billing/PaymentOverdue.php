<?php

namespace App\Notifications\Billing;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La relance a J+3 d'un prelevement d'abonnement en echec (README section 3). Courriel seulement :
 * ce n'est pas une alerte de l'equipe configurable (README section 5), c'est un message
 * transactionnel qui ne se desactive pas.
 */
class PaymentOverdue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly int $daysBeforeSuspension,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('billing.mail.overdue.subject', ['tenant' => $this->tenantName]))
            ->line(__('billing.mail.overdue.line', ['tenant' => $this->tenantName]))
            ->line(trans_choice('billing.mail.overdue.deadline', $this->daysBeforeSuspension, ['days' => $this->daysBeforeSuspension]))
            ->action(__('billing.mail.action'), url('/settings/tenants'));
    }
}
