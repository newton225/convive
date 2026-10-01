<?php

namespace App\Notifications\Tenants;

use App\Models\SupportAccessGrant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent la personne de l'equipe Convive qu'une organisation vient de lui ouvrir un acces de
 * support (README section 3) : sans ce message, elle ne le saurait qu'en ouvrant la console, et un
 * acces d'une heure pourrait expirer avant d'etre vu.
 */
class SupportAccessOpened extends Notification implements ShouldQueue
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
            ->subject(__('support_access.mail.opened.subject', ['organisation' => $tenant->name]))
            ->line(__('support_access.mail.opened.intro', [
                'organisation' => $tenant->name,
                'granted_by' => $this->grant->grantedBy->name ?? $tenant->name,
            ]))
            ->line(__('support_access.mail.opened.reason', ['reason' => (string) $this->grant->reason]))
            ->line(__('support_access.mail.opened.until', [
                'expires' => $this->grant->expires_at->translatedFormat('j F Y, H:i'),
            ]))
            ->action(__('support_access.mail.opened.action'), route('console.organisations.index'))
            ->line(__('support_access.mail.opened.outro'));
    }
}
