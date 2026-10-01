<?php

namespace App\Notifications\Console;

use App\Models\ConsoleOperator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent une personne qu'elle a ete ajoutee a l'equipe editeur (README ecran 34). Aucun jeton
 * dans le lien : c'est l'adresse verifiee de son compte qui ouvre la console.
 */
class ConsoleOperatorInvited extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public ConsoleOperator $operator, public string $inviterName)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('console.team.invitation_mail.subject'))
            ->line(__('console.team.invitation_mail.intro', [
                'inviter' => $this->inviterName,
                'profile' => $this->operator->profile->label(),
            ]))
            ->line(__('console.team.invitation_mail.instruction', ['email' => $this->operator->email]))
            ->action(__('console.team.invitation_mail.action'), route('console.home'));
    }
}
