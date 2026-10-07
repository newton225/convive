<?php

namespace App\Notifications\Tenants;

use App\Models\TenantInvitation as TenantInvitationModel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TenantInvitationModel $invitation)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
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
        $tenant = $this->invitation->tenant;
        $inviter = $this->invitation->inviter;

        // Sans compte a cette adresse, le lien mene droit a l'inscription ; sinon a la connexion
        // (TODO du 2026-10-07). Les deux pages gardent l'invitation pendant tout le parcours.
        $hasAccount = User::query()->whereRaw('lower(email) = ?', [strtolower($this->invitation->email)])->exists();
        $page = $hasAccount ? 'login' : 'register';

        return (new MailMessage)
            ->subject(__('tenants.invitation_mail.subject', ['tenant' => $tenant->name]))
            ->line(__('tenants.invitation_mail.intro', [
                'inviter' => $inviter->name,
                'tenant' => $tenant->name,
            ]))
            ->line(__($hasAccount ? 'tenants.invitation_mail.instruction' : 'tenants.invitation_mail.instruction_register', [
                'email' => $this->invitation->email,
            ]))
            ->action(
                __($hasAccount ? 'tenants.invitation_mail.action' : 'tenants.invitation_mail.action_register'),
                route($page, ['invitation' => $this->invitation->code]),
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'tenant_id' => $this->invitation->tenant_id,
            'tenant_name' => $this->invitation->tenant->name,
            'profile' => $this->invitation->profile_name,
        ];
    }
}
