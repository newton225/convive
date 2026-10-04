<?php

namespace App\Notifications\Console;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent l'equipe Convive qu'un espace n'a pas pu etre ouvert (incident du 2026-10-04) : la
 * personne a vu un message d'excuse, l'equipe doit regarder pourquoi. Le detail technique est dans le
 * journal applicatif, pas dans le courriel.
 */
class TenantCreationFailed extends Notification
{
    public function __construct(public readonly string $organisation, public readonly string $email)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('console.alerts.tenant_creation_failed.subject'))
            ->line(__('console.alerts.tenant_creation_failed.intro', ['organisation' => $this->organisation, 'email' => $this->email]))
            ->line(__('console.alerts.tenant_creation_failed.action'));
    }
}
