<?php

namespace App\Notifications\Events;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent l'organisation que l'equipe Convive a retire son annonce de la vitrine, avec le motif
 * (README section 3 : « avec un motif transmis a l'organisation »). Courriel seulement, comme les
 * messages de facturation : ce n'est pas une alerte que le destinataire peut desactiver.
 */
class AnnouncementWithdrawnByEditor extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventName,
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
            ->subject(__('events.announcement_withdrawn_mail.subject', ['event' => $this->eventName]))
            ->line(__('events.announcement_withdrawn_mail.intro', ['event' => $this->eventName]))
            ->line(__('events.announcement_withdrawn_mail.reason', ['reason' => $this->reason]))
            ->line(__('events.announcement_withdrawn_mail.outro'));
    }
}
