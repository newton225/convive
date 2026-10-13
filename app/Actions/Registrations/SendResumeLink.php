<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Notifications\Registrations\ResumeLink;
use App\Support\GuestMessageQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Renvoie a l'invite le lien de sa reservation en cours, quand il l'a perdu (decision du
 * 2026-10-12). Le lien est toujours envoye au telephone de l'inscription, jamais a celui que la
 * personne saisit : saisir le numero d'un autre ne lui donne rien.
 */
class SendResumeLink
{
    /**
     * Send a fresh resume link to the phone that holds a running reservation, or do nothing.
     *
     * Seule l'empreinte du jeton de reprise est stockee : le seul moyen de rendre un lien qui
     * permet de finaliser est d'en emettre un neuf, l'ancien cesse alors de fonctionner. L'envoi
     * est immediat et non mis en file, pour que le jeton en clair ne soit ecrit dans aucune file
     * d'attente ; il est dans la meme transaction que le changement de jeton, pour qu'un envoi
     * en echec ne prive pas l'invite de l'ancien lien.
     *
     * Le retour dit si un message est parti ; l'appelant n'en tire jamais une reponse differente
     * pour l'invite, pour ne pas confirmer qu'un numero a une reservation.
     */
    public function handle(Event $event, string $phone): bool
    {
        $registration = Registration::phoneHolder($event, $phone);

        if ($registration === null || $registration->status !== RegistrationStatus::Held) {
            return false;
        }

        // Quota d'envois du plan (SECURITY.md H5) : un message refuse n'est pas envoye.
        if (! GuestMessageQuota::allows()) {
            return false;
        }

        DB::transaction(function () use ($event, $registration) {
            $resume = Registration::generateResumeToken();

            $registration->update(['resume_token_hash' => Registration::hashResumeToken($resume)]);

            $link = route('public.registrations.show', ['token' => $event->public_token, 'resume' => $resume]);

            Notification::route('whatsapp', $registration->phone)
                ->route('mail', $registration->email)
                ->notifyNow(new ResumeLink($registration, $link));
        });

        GuestMessageQuota::record();

        activity()
            ->performedOn($registration)
            ->event('updated')
            ->log('registration.resume_link_resent');

        return true;
    }
}
