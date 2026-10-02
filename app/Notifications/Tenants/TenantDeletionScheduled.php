<?php

namespace App\Notifications\Tenants;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Previent les Proprietaires que leur organisation vient d'etre supprimee (README section 3) : par
 * qui, quand elle sera reellement effacee, et qu'elle peut etre restauree d'ici la. Une
 * suppression faite par un seul Proprietaire ne doit pas passer inapercue des autres.
 *
 * Les valeurs sont recopiees a l'envoi : le message est en file, et l'organisation n'est plus
 * lisible par les requetes ordinaires une fois supprimee.
 */
class TenantDeletionScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantName,
        public readonly string $deletedByName,
        public readonly string $eraseAt,
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
        $support = config('convive.support_email');

        return (new MailMessage)
            ->subject(__('tenants.mail.deletion_scheduled.subject', ['organisation' => $this->tenantName]))
            ->line(__('tenants.mail.deletion_scheduled.intro', [
                'organisation' => $this->tenantName,
                'deleted_by' => $this->deletedByName,
            ]))
            ->line(__('tenants.mail.deletion_scheduled.erase_at', [
                'date' => Carbon::parse($this->eraseAt)->translatedFormat('j F Y'),
            ]))
            ->line(is_string($support) && $support !== ''
                ? __('tenants.mail.deletion_scheduled.restore_with_address', ['email' => $support])
                : __('tenants.mail.deletion_scheduled.restore'));
    }
}
