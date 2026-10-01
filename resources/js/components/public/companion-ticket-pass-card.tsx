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
};

/**
 * Le billet d'un accompagnateur sur la page de l'invite (README 2.8, ecran 7) : le meme talon que
 * celui de l'invite, et de quoi lui transmettre son lien individuel s'il arrive seul. Le partage
 * passe par `wa.me`, qui ouvre WhatsApp sans rien envoyer tant que l'invite n'a pas choisi le
 * destinataire.
 */
export function CompanionTicketPassCard({ pass, design, event }: Props) {
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
            {shareHref && pass.shareUrl ? (
                // Une hauteur fixe et commune, 44 px (cible tactile du parcours invite) : les deux
                // boutons n'ont pas la meme variante, ils ne doivent pas pour autant se decaler.
                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild size="sm" className="h-11">
                        <a
                            href={shareHref}
                            target="_blank"
                            rel="noopener noreferrer"
                            data-test="ticket-pass-share"
                        >
                            <MessageCircle />
                            {t('guest.ticket.share_whatsapp')}
                        </a>
                    </Button>
                    <CopyButton
                        value={pass.shareUrl}
                        label={t('guest.ticket.copy_link')}
                        className="h-11"
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
