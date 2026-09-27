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
 * Rappel J-7, J-2 ou J-1 aux inscriptions sans preuve encore validee (README 2.7), etape 8 de
 * « Ordre de construction ». Le meme texte sert aux trois echeances : seule la date d'envoi
 * change (voir `App\Actions\Tickets\SendProofReminder`), le README ne distingue pas leur
 * formulation.
 */
class ProofReminder extends Notification implements ShouldQueue
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
            subjectLine: __('guest.mail.proof_reminder.subject', ['event' => $this->registration->event->name]),
            lines: [
                __('guest.mail.proof_reminder.intro', [
                    'name' => $this->registration->name,
                    'event' => $this->registration->event->name,
                ]),
            ],
            actionText: __('guest.mail.proof_reminder.action'),
            actionUrl: $this->link,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.proof_reminder', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'link' => $this->link,
        ]);
    }
}
