<?php

namespace App\Notifications\Registrations;

use App\Mail\GuestNotificationMail;
use App\Models\Registration;
use App\Support\GuestNotificationBranding;
use App\Support\InvitationCardMessage;
use App\Support\WhatsApp\WhatsAppTemplate;
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

        // README 2.7 : l'invite recoit aussi le billet de chaque accompagnateur, a leur transmettre.
        $companions = InvitationCardMessage::companionLinks($registration);

        if ($companions !== []) {
            $lines[] = __('guest.mail.invitation_card.companions');

            foreach ($companions as $companion) {
                $lines[] = __('guest.whatsapp.companion_ticket_line', $companion);
            }
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

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('invitation_card', [
            $this->registration->name,
            $this->registration->event->name,
            $this->link,
        ]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return InvitationCardMessage::forHolder($this->registration, $this->link);
    }
}
