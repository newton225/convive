<?php

namespace App\Support;

use App\Models\Registration;
use App\Models\Ticket;

/**
 * Le texte de la carte d'invitation (README 2.7), une seule source pour l'envoi par
 * l'application et pour le message WhatsApp que l'organisateur envoie depuis son propre
 * telephone : les deux doivent dire la meme chose.
 *
 * La carte de l'invite principal porte le lien de chacun des billets de ses accompagnateurs :
 * il peut tout recevoir et se charger de les leur transmettre. Un accompagnateur ne recoit que
 * son propre billet, jamais la carte qui donne acces a tout le groupe.
 */
class InvitationCardMessage
{
    /**
     * The WhatsApp text of the main guest's card : the card link, then one line per companion.
     */
    public static function forHolder(Registration $registration, string $link): string
    {
        $lines = [__('guest.whatsapp.invitation_card', [
            'name' => $registration->name,
            'event' => $registration->event->name,
            'link' => $link,
        ])];

        $companions = self::companionLinks($registration);

        if ($companions !== []) {
            $lines[] = __('guest.whatsapp.companion_tickets_intro');

            foreach ($companions as $companion) {
                $lines[] = __('guest.whatsapp.companion_ticket_line', $companion);
            }
        }

        return implode("\n", $lines);
    }

    /**
     * The WhatsApp text for one companion : their own ticket only.
     */
    public static function forCompanion(Ticket $ticket, string $link): string
    {
        return __('guest.whatsapp.companion_ticket', [
            'name' => $ticket->holder_name ?? '',
            'event' => $ticket->registration->event->name,
            'link' => $link,
        ]);
    }

    /**
     * Name and individual link of each companion's ticket, in the group's order.
     *
     * @return array<int, array{name: string, link: string}>
     */
    public static function companionLinks(Registration $registration): array
    {
        $links = [];

        foreach (self::companionTickets($registration) as $ticket) {
            $link = $ticket->shareUrl();

            if ($link !== null) {
                $links[] = ['name' => $ticket->holder_name ?? '', 'link' => $link];
            }
        }

        return $links;
    }

    /**
     * @return array<int, Ticket>
     */
    public static function companionTickets(Registration $registration): array
    {
        return $registration->tickets
            ->where('holder_position', '>', Ticket::GuestPosition)
            ->sortBy('holder_position')
            ->each(fn (Ticket $ticket) => $ticket->setRelation('registration', $registration))
            ->values()
            ->all();
    }
}
