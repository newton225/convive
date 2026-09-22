<?php

namespace App\Notifications\Registrations;

use App\Models\Registration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
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

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('guest.mail.proof_reminder.subject', ['event' => $this->registration->event->name]))
            ->line(__('guest.mail.proof_reminder.intro', [
                'name' => $this->registration->name,
                'event' => $this->registration->event->name,
            ]))
            ->action(__('guest.mail.proof_reminder.action'), $this->link);
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
