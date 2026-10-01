<?php

namespace App\Notifications\Tenants;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Previent les Proprietaires qu'un acces de support s'est termine (README section 3) : par qui il
 * etait tenu, pourquoi il s'est ferme, combien de pages ont ete consultees, et la note laissee par
 * la personne de l'equipe Convive quand elle l'a ferme elle-meme. Courriel seulement, comme les
 * messages de facturation : ouvrir son contenu a l'editeur n'est pas une alerte qu'on desactive.
 *
 * Les valeurs sont recopiees a l'envoi plutot que relues sur le modele : le message est en file,
 * et doit dire ce qui etait vrai au moment ou l'acces s'est ferme.
 */
class SupportAccessEnded extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $endReason  `finished`, `revoked` ou `expired`
     */
    public function __construct(
        public readonly string $tenantName,
        public readonly string $tenantSlug,
        public readonly string $operatorName,
        public readonly string $endReason,
        public readonly int $viewsCount,
        public readonly ?string $closingNote = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('support_access.mail.ended.subject', ['organisation' => $this->tenantName]))
            ->line(__("support_access.mail.ended.reasons.{$this->endReason}", [
                'operator' => $this->operatorName,
                'organisation' => $this->tenantName,
            ]))
            ->line(trans_choice('support_access.mail.ended.views', $this->viewsCount, ['count' => $this->viewsCount]));

        if ($this->closingNote !== null) {
            $mail->line(__('support_access.mail.ended.note', ['note' => $this->closingNote]));
        }

        return $mail->action(
            __('support_access.mail.ended.action'),
            route('tenants.support-access.show', $this->tenantSlug),
        );
    }
}
