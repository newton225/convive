<?php

namespace App\Notifications\Console;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent une personne que l'equipe Convive a reinitialise sa double authentification (README
 * section 3). Le message part toujours : si la personne n'a rien demande, c'est par lui qu'elle
 * apprend que quelqu'un s'est fait passer pour elle aupres du support.
 */
class TwoFactorResetByEditor extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject(__('console.accounts.mail.two_factor_reset.subject'))
            ->line(__('console.accounts.mail.two_factor_reset.intro'))
            ->line(__('console.accounts.mail.two_factor_reset.next'))
            ->line(__('console.accounts.mail.two_factor_reset.not_you'));
    }
}
