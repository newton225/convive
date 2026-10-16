import { MessageCircle } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { BrandedTicket } from '@/components/ticket-template/branded-ticket';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type {
    CompanionTicketPass,
    TicketCardEvent,
    TicketDesign,
} from '@/types';

type Props = {
    pass: CompanionTicketPass;
    design: TicketDesign;
    event: TicketCardEvent;
    // Apercu de l'organisation : ni partage ni copie, ils s'adressent a l'invite. L'organisation
    // transmet les liens depuis la fenetre « Carte » de la base d'inscrits.
    preview?: boolean;
};

/**
 * Le billet d'un accompagnateur sur la page de l'invite (README 2.8, ecran 7) : le meme talon que
 * celui de l'invite, et de quoi lui transmettre son lien individuel s'il arrive seul. Le partage
 * passe par `wa.me`, qui ouvre WhatsApp sans rien envoyer tant que l'invite n'a pas choisi le
 * destinataire.
 */
export function CompanionTicketPassCard({
    pass,
    design,
    event,
    preview = false,
}: Props) {
    const { t } = useTranslation();
    const shareHref = pass.shareUrl
        ? `https://wa.me/?text=${encodeURIComponent(
              t('guest.ticket.share_message', {
                  event: event.name,
                  url: pass.shareUrl,
              }),
          )}`
        : null;

    return (
        <div className="space-y-3" data-test="ticket-pass">
            <BrandedTicket {...design} event={event} ticket={pass.card} />
            {preview ? null : shareHref && pass.shareUrl ? (
                // Empiles sur toute la largeur du billet, de meme taille : les libelles portent le nom
                // de la personne et sont longs, ils passent a la ligne plutot que de decaler les
                // boutons. 44 px au moins (cible tactile du parcours invite).
                <div className="grid gap-2">
                    <Button
                        asChild
                        size="sm"
                        className="h-auto min-h-11 w-full py-2 whitespace-normal"
                    >
                        <a
                            href={shareHref}
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="ticket-pass-share"
                        >
                            <MessageCircle />
                            {t('guest.ticket.share_pass_whatsapp', {
                                name: pass.name,
                            })}
                        </a>
                    </Button>
                    <CopyButton
                        value={pass.shareUrl}
                        label={t('guest.ticket.copy_pass_link', {
                            name: pass.name,
                        })}
                        className="h-auto min-h-11 w-full py-2 whitespace-normal"
                    />
                </div>
            ) : (
                <p className="text-muted-foreground text-xs">
                    {t('guest.ticket.share_unavailable')}
                </p>
            )}
        </div>
    );
}
