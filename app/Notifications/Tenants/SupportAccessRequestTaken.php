<?php

namespace App\Notifications\Tenants;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent les Proprietaires qu'une personne de l'equipe Convive a pris leur demande d'aide en
 * charge (README section 3) : son nom apparait maintenant sur l'ecran d'acces du support, il reste
 * a lui ouvrir l'acces. Rien n'est ouvert par ce message.
 */
class SupportAccessRequestTaken extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly string $operatorName,
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
            ->subject(__('support_access.mail.taken.subject', ['operator' => $this->operatorName]))
            ->line(__('support_access.mail.taken.intro', [
                'operator' => $this->operatorName,
                'organisation' => $this->tenantName,
            ]))
            ->line(__('support_access.mail.taken.next'))
            ->action(
                __('support_access.mail.taken.action'),
                route('tenants.support-access.show', $this->tenantSlug),
            );
    }
}
