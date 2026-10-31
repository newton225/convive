<?php

namespace App\Notifications\Tenants;

use App\Enums\PaymentAccountOutcome;
use App\Models\Tenant;
use App\Support\WhatsApp\WhatsAppTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent de l'issue d'une demande de changement d'un compte de versement : validee par un second
 * proprietaire, annulee, ou appliquee d'elle-meme a la fin du delai (decision du 2026-10-31). Le numero affiche
 * aux invites change a ce moment-la, c'est le point le plus sensible du produit pour l'argent.
 *
 * Ne porte que des valeurs simples, jamais le compte lui-meme : une creation annulee supprime le compte, et un
 * modele serialise dans la file ne se retrouverait plus.
 */
class PaymentAccountDecided extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Tenant $tenant,
        public PaymentAccountOutcome $outcome,
        public string $label,
        public ?string $number,
        public ?string $actorName,
    ) {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'whatsapp'];
    }

    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('payment_account_decided', [
            $this->tenant->name,
            $this->label,
            $this->sentence(),
            $this->numberText(),
        ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return __('payment_accounts.decided.whatsapp', [
            'tenant' => $this->tenant->name,
            'label' => $this->label,
            'sentence' => $this->sentence(),
            'number' => $this->numberText(),
        ]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('payment_accounts.decided.subject.'.$this->outcome->value, ['tenant' => $this->tenant->name]))
            ->line(__('payment_accounts.decided.intro', [
                'label' => $this->label,
                'tenant' => $this->tenant->name,
                'sentence' => $this->sentence(),
            ]))
            ->line(__($this->outcome->isApplied() ? 'payment_accounts.decided.number_shown' : 'payment_accounts.decided.number_kept', [
                'value' => $this->numberText(),
            ]))
            ->line(__('payment_accounts.decided.outro'));
    }

    /**
     * Ce qui s'est passe, en une phrase, sans le numero.
     */
    private function sentence(): string
    {
        return __('payment_accounts.decided.sentence.'.$this->outcome->value, [
            'actor' => $this->actorName ?? '',
        ]);
    }

    private function numberText(): string
    {
        return $this->number ?? __('payment_accounts.mail.none');
    }
}
