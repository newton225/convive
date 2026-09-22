<?php

namespace App\Notifications\Tenants;

use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent immediatement de toute demande de changement sur un compte de versement, avec
 * l'ancien et le nouveau numero.
 *
 * C'est le controle qui rend le delai d'activation utile : sans cette alerte, personne ne
 * regarde pendant les vingt-quatre heures ou le changement pourrait encore etre annule.
 */
class PaymentAccountChanged extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|null>  $before
     */
    public function __construct(
        public Tenant $tenant,
        public PaymentAccount $account,
        public array $before,
        public User $actor,
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * WhatsApp est prevu par le README, derriere une interface dediee qui n'existe pas encore.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('payment_accounts.mail.subject', ['tenant' => $this->tenant->name]))
            ->line(__('payment_accounts.mail.intro', [
                'actor' => $this->actor->name,
                'label' => $this->account->label,
                'tenant' => $this->tenant->name,
            ]))
            ->line(__('payment_accounts.mail.before', [
                'value' => $this->before['account_number'] ?? __('payment_accounts.mail.none'),
            ]))
            ->line(__('payment_accounts.mail.after', [
                'value' => $this->account->pending_account_number ?? __('payment_accounts.mail.none'),
            ]));

        if ($this->account->pending_activates_at) {
            $message->line(__('payment_accounts.mail.activates_at', [
                'date' => $this->account->pending_activates_at->translatedFormat('d/m/Y H:i'),
            ]));
        }

        return $message->line(__('payment_accounts.mail.outro'));
    }
}
