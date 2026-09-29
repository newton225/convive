<?php

namespace App\Mail;

use App\Support\ReadableTextColor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Carte d'invitation et rappels au parcours invite (README 2.7, etape 8), aux couleurs de
 * marque du locataire : CLAUDE.md, « Les couleurs de marque du locataire s'appliquent
 * uniquement au parcours invite, au billet et aux messages ». Les alertes internes
 * (`App\Notifications\Tenants\PaymentAccountChanged`, etc.) restent volontairement sur le
 * gabarit texte par defaut des notifications Laravel : le back-office reste neutre.
 *
 * Retourne depuis `toMail()` d'une Notification plutot qu'un `MailMessage` : point d'extension
 * officiel de Laravel (« a notification's toMail method may also return a Mailable instance »),
 * necessaire ici puisque le theme des notifications Markdown est global a l'application, pas
 * calculable par instance a partir des couleurs d'un locataire.
 *
 * Ne recoit que des chaines, jamais l'evenement ni le locataire : cette classe peut etre mise
 * en file (`ShouldQueue`), donc executee par un travailleur plus tard, sans garantie que la
 * tenancy soit encore initialisee a ce moment (CLAUDE.md, « Multi-locataire »). Tout ce qui
 * depend du locataire (nom, couleurs) doit deja etre resolu par l'appelant, a l'interieur de la
 * tenancy active, exactement comme `Registration::signedResumeUrl()` precalcule son lien avant
 * de le confier a la notification.
 *
 * Aucun logo : les URL de fichiers de marque sont signees et expirent en 30 minutes
 * (CLAUDE.md, « Fichiers de marque »), inadaptees a un email souvent ouvert bien plus tard.
 */
class GuestNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public string $organisationName,
        public string $primaryColor,
        public string $secondaryColor,
        public string $subjectLine,
        public array $lines,
        public string $actionText,
        public string $actionUrl,
    ) {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.guest.notification',
            with: [
                'organisationName' => $this->organisationName,
                'primaryColor' => $this->primaryColor,
                'primaryTextColor' => ReadableTextColor::on($this->primaryColor),
                'secondaryColor' => $this->secondaryColor,
                'secondaryTextColor' => ReadableTextColor::on($this->secondaryColor),
                'lines' => $this->lines,
                'actionText' => $this->actionText,
                'actionUrl' => $this->actionUrl,
            ],
        );
    }
}
