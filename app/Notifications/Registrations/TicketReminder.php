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
 * Rappel jour J moins 3 heures aux billets valides (README 2.7), etape 8 de « Ordre de
 * construction ».
 */
class TicketReminder extends Notification implements ShouldQueue
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
        $colors = $this->registration->event->colors();

        return (new GuestNotificationMail(
            organisationName: GuestNotificationBranding::organisationName(),
            primaryColor: $colors['primary'],
            secondaryColor: $colors['secondary'],
            subjectLine: __('guest.mail.ticket_reminder.subject', ['event' => $this->registration->event->name]),
            lines: [
                __('guest.mail.ticket_reminder.intro', [
                    'name' => $this->registration->name,
                    'event' => $this->registration->event->name,
                ]),
            ],
            actionText: __('guest.mail.ticket_reminder.action'),
            actionUrl: $this->link,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.ticket_reminder', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'link' => $this->link,
        ]);
    }
}
