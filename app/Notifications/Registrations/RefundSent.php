<?php

namespace App\Notifications\Registrations;

use App\Mail\GuestNotificationMail;
use App\Models\Registration;
use App\Support\GuestNotificationBranding;
use App\Support\Money;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;

/**
 * Remboursement marque apres coup sur une annulation « a rembourser » (README 2.11) : l'invite,
 * prevenu a l'annulation d'un remboursement en cours, apprend qu'il est parti et combien il
 * recoit, frais deduits.
 */
class RefundSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Registration $registration)
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
            subjectLine: __('guest.mail.refund_sent.subject', ['event' => $this->registration->event->name]),
            lines: [
                __('guest.mail.refund_sent.intro', [
                    'name' => $this->registration->name,
                    'event' => $this->registration->event->name,
                ]),
                $this->refundLine(),
            ],
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('refund_sent', [
            $this->registration->name,
            $this->registration->event->name,
            ...array_values($this->refundDetails()),
        ]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return __('guest.whatsapp.refund_sent', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
        ]).' '.$this->refundLine();
    }

    private function refundLine(): string
    {
        return __('guest.refund.refunded', $this->refundDetails());
    }

    /**
     * Le montant recu, la date, le moyen et les frais, dans l'ordre des variables du modele.
     *
     * @return array{amount: string, date: string, channel: string, fee: string}
     */
    private function refundDetails(): array
    {
        $registration = $this->registration;

        return [
            'amount' => Money::format($registration->netRefund() ?? 0),
            'date' => $registration->refunded_on?->isoFormat('LL') ?? '',
            'channel' => $registration->refund_channel?->label() ?? '',
            'fee' => Money::format($registration->refund_fee ?? 0),
        ];
    }
}
