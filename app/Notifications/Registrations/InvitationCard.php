<?php

namespace App\Notifications\Registrations;

use App\Mail\GuestNotificationMail;
use App\Models\Registration;
use App\Support\GuestNotificationBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * La carte d'invitation (README 2.7, ecran 7), etape 8 de « Ordre de construction ». Envoyee a
 * la validation de la preuve si l'echeance programmee est deja passee, sinon par la tache
 * planifiee qui la surveille (voir `App\Actions\Tickets\SendInvitationCard`).
 *
 * WhatsApp est le canal systematique (le telephone est toujours recueilli, README 2.5) ;
 * l'email s'y ajoute quand l'invite l'a fourni.
 */
class InvitationCard extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Registration $registration, public string $link)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        $channels = ['whatsapp'];

        if ($notifiable->routeNotificationFor('mail', $this) !== null) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(mixed $notifiable): Mailable
    {
        $registration = $this->registration;
        $tableNumber = $registration->tableAssignment?->seatingTable->number;
        $colors = $registration->event->colors();

        $lines = [
            __('guest.mail.invitation_card.intro', [
                'name' => $registration->name,
                'event' => $registration->event->name,
            ]),
        ];

        if ($tableNumber !== null) {
            $lines[] = __('guest.mail.invitation_card.table', ['number' => $tableNumber]);
        }

        return (new GuestNotificationMail(
            organisationName: GuestNotificationBranding::organisationName(),
            primaryColor: $colors['primary'],
            secondaryColor: $colors['secondary'],
            subjectLine: __('guest.mail.invitation_card.subject', ['event' => $registration->event->name]),
            lines: $lines,
            actionText: __('guest.mail.invitation_card.action'),
            actionUrl: $this->link,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.invitation_card', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'link' => $this->link,
        ]);
    }
}
