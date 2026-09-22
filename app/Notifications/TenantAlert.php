<?php

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Une alerte de l'application (README section 5) : dans la cloche, par courriel, ou les deux, au
 * choix du destinataire pour ce type.
 *
 * Le texte n'est pas stocke : seuls le type et ses parametres le sont (voir
 * `NotificationType::message()`). Le lien est un chemin relatif construit a l'envoi, jamais une
 * URL complete : la tache planifiee qui produit certaines alertes n'a pas de requete dont
 * deriver l'hote.
 */
class TenantAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly NotificationType $type,
        public readonly array $params,
        public readonly string $url,
        public readonly ?int $tenantId = null,
        public readonly ?string $tenantName = null,
    ) {}

    /**
     * Get the channels this alert goes through, following the recipient's own choice.
     *
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->notificationChannelFor($this->type)->laravelChannels();
    }

    /**
     * Get the stored form of the alert.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return [
            'type' => $this->type->value,
            'params' => $this->params,
            'url' => $this->url,
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName,
        ];
    }

    /**
     * Get the email form of the alert.
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('notifications.mail.subject', ['app' => config('app.name')]))
            ->line($this->type->message($this->params))
            ->action(__('notifications.mail.action'), url($this->url));
    }
}
