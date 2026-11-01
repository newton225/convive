<?php

namespace App\Notifications\Registrations;

use App\Mail\GuestNotificationMail;
use App\Models\Registration;
use App\Support\GuestNotificationBranding;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Previent l'invite principal que sa table a change apres un deplacement dans le plan de salle (decision du
 * 2026-10-31). Le billet affiche deja la bonne table a chaque ouverture ; ce message sert a ceux qui avaient
 * garde l'ancienne (PDF enregistre, carte lue avant le changement). Envoye par `App\Jobs\NotifyTableChange`,
 * jamais directement par le geste de deplacement.
 *
 * WhatsApp est le canal systematique, le courriel s'y ajoute quand l'invite en a donne un, comme la carte.
 */
class TableChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Registration $registration,
        public int $tableNumber,
        public string $link,
    ) {
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
        $colors = $registration->event->colors();

        return (new GuestNotificationMail(
            organisationName: GuestNotificationBranding::organisationName(),
            primaryColor: $colors['primary'],
            secondaryColor: $colors['secondary'],
            subjectLine: __('guest.mail.table_changed.subject', ['event' => $registration->event->name]),
            lines: [
                __('guest.mail.table_changed.intro', [
                    'name' => $registration->name,
                    'event' => $registration->event->name,
                    'number' => $this->tableNumber,
                ]),
            ],
            actionText: __('guest.mail.table_changed.action'),
            actionUrl: $this->link,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('table_changed', [
            $this->registration->name,
            $this->registration->event->name,
            (string) $this->tableNumber,
            $this->link,
        ]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.table_changed', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'number' => $this->tableNumber,
            'link' => $this->link,
        ]);
    }
}
