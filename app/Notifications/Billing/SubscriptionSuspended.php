<?php

namespace App\Notifications\Billing;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * L'annonce de la suspension de l'espace a J+10 d'impaye (README section 3). Courriel seulement,
 * comme `PaymentOverdue`.
 */
class SubscriptionSuspended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $tenantName) {}

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
            ->subject(__('billing.mail.suspended.subject', ['tenant' => $this->tenantName]))
            ->line(__('billing.mail.suspended.line', ['tenant' => $this->tenantName]))
            ->action(__('billing.mail.action'), url('/settings/tenants'));
    }
}
