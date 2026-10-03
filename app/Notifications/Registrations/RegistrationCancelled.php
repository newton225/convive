<?php

namespace App\Notifications\Registrations;

use App\Enums\RefundStatus;
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
 * Annulation d'une inscription par l'organisation (README 2.11) : l'invite apprend le motif et
 * le sort de son paiement ici, plutot qu'a l'entree quand son billet est refuse.
 */
class RegistrationCancelled extends Notification implements ShouldQueue
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
            subjectLine: __('guest.mail.registration_cancelled.subject', ['event' => $this->registration->event->name]),
            lines: array_values(array_filter([
                __('guest.mail.registration_cancelled.intro', [
                    'name' => $this->registration->name,
                    'event' => $this->registration->event->name,
                    'reason' => $this->registration->cancellation_reason,
                ]),
                $this->paymentLine(),
            ])),
        ))->to($notifiable->routeNotificationFor('mail', $this));
    }

    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('registration_cancelled', [
            $this->registration->name,
            $this->registration->event->name,
            (string) $this->registration->cancellation_reason,
        ]);
    }

    public function toWhatsApp(mixed $notifiable): string
    {
        return trim(__('guest.whatsapp.registration_cancelled', [
            'name' => $this->registration->name,
            'event' => $this->registration->event->name,
            'reason' => $this->registration->cancellation_reason,
        ]).' '.($this->paymentLine() ?? ''));
    }

    /**
     * The sentence about the payment, or null when nothing was collected.
     */
    private function paymentLine(): ?string
    {
        $registration = $this->registration;

        if ($registration->refund_status === RefundStatus::Due) {
            return __('guest.refund.due', ['amount' => Money::format($registration->amount_due)]);
        }

        if ($registration->refund_status === RefundStatus::Refunded) {
            return __('guest.refund.refunded', [
                'amount' => Money::format($registration->netRefund() ?? 0),
                'date' => $registration->refunded_on?->isoFormat('LL') ?? '',
                'channel' => $registration->refund_channel?->label() ?? '',
                'fee' => Money::format($registration->refund_fee ?? 0),
            ]);
        }

        if ($registration->refund_status === RefundStatus::Kept) {
            return __('guest.refund.kept', ['reason' => $registration->refund_kept_reason ?? '']);
        }

        return null;
    }
}
