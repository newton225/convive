<?php

namespace App\Notifications\Tenants;

use App\Models\SupportAccessGrant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent la personne de l'equipe Convive que l'organisation a prolonge son acces : elle sait
 * jusqu'a quand elle peut encore lire, sans rouvrir la console pour le verifier.
 */
class SupportAccessExtended extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportAccessGrant $grant) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $tenant = $this->grant->tenant;

        return (new MailMessage)
            ->subject(__('support_access.mail.extended.subject', ['organisation' => $tenant->name]))
            ->line(__('support_access.mail.extended.intro', ['organisation' => $tenant->name]))
            ->line(__('support_access.mail.opened.until', [
                'expires' => $this->grant->expires_at->translatedFormat('j F Y, H:i'),
            ]))
            ->action(__('support_access.mail.opened.action'), route('console.organisations.index'));
    }
}
