<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use App\Notifications\Registrations\ResumeLink;
use App\Support\GuestMessageQuota;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Renvoie a l'invite le lien de son dossier en cours (reservation avec decompte, ou preuve
 * rejetee), quand il l'a perdu (decisions du 2026-10-12 et du 2026-10-16). Le lien est toujours
 * envoye au telephone de l'inscription, jamais a celui que la personne saisit : saisir le numero
 * d'un autre ne lui donne rien. L'organisation peut aussi le renvoyer depuis la base d'inscrits
 * (`sendFor`), sans jamais le voir : il part directement a l'invite.
 */
class SendResumeLink
{
    /**
     * Send a fresh resume link to the phone that holds a running reservation or a rejected proof, or do nothing.
     *
     * Le retour dit si un message est parti ; l'appelant n'en tire jamais une reponse differente
     * pour l'invite, pour ne pas confirmer qu'un numero a une reservation.
     */
    public function handle(Event $event, string $phone): bool
    {
        // Une reservation avec decompte, ou un dossier dont la preuve a ete rejetee (il attend une
        // nouvelle preuve : sans ce lien, l'invite ne le retrouve pas).
        $registration = Registration::phoneHolder($event, $phone) ?? Registration::rejectedHolder($event, $phone);

        if ($registration === null) {
            return false;
        }

        return $this->sendFor($registration);
    }

    /**
     * Send a fresh resume link for this registration.
     *
     * Seule l'empreinte du jeton de reprise est stockee : le seul moyen de rendre un lien qui
     * permet de finaliser est d'en emettre un neuf, l'ancien cesse alors de fonctionner. L'envoi
     * est immediat et non mis en file, pour que le jeton en clair ne soit ecrit dans aucune file
     * d'attente ; il est dans la meme transaction que le changement de jeton, pour qu'un envoi
     * en echec ne prive pas l'invite de l'ancien lien.
     *
     * `$sentBy` : renvoi par l'organisation, journalise avec son auteur.
     */
    public function sendFor(Registration $registration, ?User $sentBy = null): bool
    {
        if (! in_array($registration->status, [RegistrationStatus::Held, RegistrationStatus::ProofRejected], true)) {
            return false;
        }

        // Quota d'envois du plan (SECURITY.md H5) : un message refuse n'est pas envoye.
        if (! GuestMessageQuota::allows()) {
            return false;
        }

        // Adresse construite a la main, comme `Event::publicUrl()` : appelee depuis le back-office,
        // `route()` prendrait l'hote du domaine central et non le sous-domaine de l'organisation.
        $baseUrl = $registration->event->publicUrl();

        if ($baseUrl === null) {
            return false;
        }

        DB::transaction(function () use ($baseUrl, $registration) {
            $resume = Registration::generateResumeToken();

            $registration->update(['resume_token_hash' => Registration::hashResumeToken($resume)]);

            $link = "{$baseUrl}/register/{$resume}";

            Notification::route('whatsapp', $registration->phone)
                ->route('mail', $registration->email)
                ->notifyNow(new ResumeLink($registration, $link));
        });

        GuestMessageQuota::record();

        $activity = activity()
            ->performedOn($registration)
            ->event('updated');

        if ($sentBy !== null) {
            $activity->causedBy($sentBy)->withProperties(['phone' => $registration->phone, 'email' => $registration->email])
                ->log('registrations.resume_link_sent');
        } else {
            $activity->log('registration.resume_link_resent');
        }

        return true;
    }
}
