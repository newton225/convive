<?php

namespace App\Notifications\Registrations;

use App\Mail\GuestNotificationMail;
use App\Models\Registration;
use App\Support\GuestNotificationBranding;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Le lien de reprise renvoye a l'invite qui l'a perdu (`App\Actions\Registrations\SendResumeLink`).
 *
 * Volontairement hors file d'attente : le lien porte le jeton de reprise en clair, qui ne doit
 * etre ecrit nulle part, pas meme dans la charge d'une tache.
 */
class ResumeLink extends Notification
{
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
            subjectLine: __('guest.mail.resume_link.subject', ['event' => $this->registration->event->name]),
            lines: [
                __('guest.mail.resume_link.intro', [
                    'name' => $this->registration->name,
                    'event' => $this->registration->event->name,
                ]),
            ],
            actionText: __('guest.mail.resume_link.action'),
            actionUrl: $this->link,
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('resume_link', [
            $this->registration->name,
            $this->registration->event->name,
            $this->link,
        ]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.resume_link', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'link' => $this->link,
        ]);
    }
}
