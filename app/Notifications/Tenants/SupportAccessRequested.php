<?php

namespace App\Notifications\Tenants;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent l'equipe Convive qu'une organisation demande de l'aide (README section 3) alors que
 * personne n'est visible pour recevoir un acces. Le message ne donne aucun acces : celui qui prend
 * la demande en charge se rend visible, et l'organisation lui ouvre ensuite son espace.
 *
 * Les valeurs sont recopiees a l'envoi : le message est en file, et la demande peut avoir ete
 * annulee entre-temps.
 */
class SupportAccessRequested extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $requestedByName,
        public readonly string $reason,
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
            ->subject(__('support_access.mail.requested.subject', ['organisation' => $this->tenantName]))
            ->line(__('support_access.mail.requested.intro', [
                'organisation' => $this->tenantName,
                'requested_by' => $this->requestedByName,
            ]))
            ->line(__('support_access.mail.requested.reason', ['reason' => $this->reason]))
            ->action(__('support_access.mail.requested.action'), route('console.organisations.index'))
            ->line(__('support_access.mail.requested.outro'));
    }
}
