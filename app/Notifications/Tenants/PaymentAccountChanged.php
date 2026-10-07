<?php

namespace App\Notifications\Tenants;

use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Support\WhatsApp\WhatsAppTemplate;
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
     * WhatsApp derriere `App\Contracts\WhatsAppSender` (CLAUDE.md, « Envois programmes et
     * rappels ») : palliatif journalise tant qu'aucun identifiant Business API n'est fourni,
     * jamais un envoi ad hoc depuis l'Action. Toujours tente : `routeNotificationForWhatsapp()`
     * renvoie `null` sans numero renseigne, et le canal saute simplement l'envoi, comme `mail`
     * le fait deja pour une inscription sans email.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'whatsapp'];
    }

    /**
     * Get the WhatsApp representation of the notification.
     *
     * Message court, sans mise en forme : c'est ce que porte le contrat `WhatsAppSender`.
     */
    /**
     * Get the WhatsApp template of this message and its variables, in the template's order.
     */
    public function whatsAppTemplate(mixed $notifiable): WhatsAppTemplate
    {
        return new WhatsAppTemplate('payment_account_changed', [
            $this->tenant->name,
            $this->account->label,
            (string) ($this->before['account_number'] ?? __('payment_accounts.mail.none')),
            $this->newNumber(),
        ]);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return __('payment_accounts.whatsapp.alert', [
            'tenant' => $this->tenant->name,
            'label' => $this->account->label,
            'before' => $this->before['account_number'] ?? __('payment_accounts.mail.none'),
            'after' => $this->newNumber(),
        ]);
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
                'value' => $this->newNumber(),
            ]));

        if ($this->account->pending_activates_at) {
            $message->line(__('payment_accounts.mail.activates_at', [
                'date' => $this->account->pending_activates_at->translatedFormat('d/m/Y H:i'),
            ]));

            return $message->line(__('payment_accounts.mail.outro'));
        }

        // Avant la premiere publication, le changement s'applique tout de suite (decision du
        // 2026-10-07) : il n'y a plus rien a annuler, seulement des acces a verifier.
        return $message
            ->line(__('payment_accounts.mail.applied_now'))
            ->line(__('payment_accounts.mail.outro_immediate'));
    }

    /**
     * The number the account now points to : the pending one during the delay, the live one when
     * the change applied at once.
     */
    private function newNumber(): string
    {
        $number = $this->account->hasPendingChange()
            ? $this->account->pending_account_number
            : $this->account->account_number;

        return $number ?? __('payment_accounts.mail.none');
    }
}
