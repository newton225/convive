import { MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { CompanionTicketPass } from '@/types';

type Props = {
    passes: CompanionTicketPass[];
    eventName: string;
};

/**
 * Transmettre d'un geste les billets de tous les accompagnateurs (README 2.8) : un seul message
 * WhatsApp qui porte le lien individuel de chacun, a envoyer a un groupe ou a un proche. Comme le
 * partage d'un billet seul, il passe par `wa.me` : rien ne part tant que l'invite n'a pas choisi
 * le destinataire. Absent tant qu'il n'y a pas au moins deux liens a transmettre.
 */
export function ShareAllTicketsButton({ passes, eventName }: Props) {
    const { t } = useTranslation();
    const lines = passes.flatMap((pass) =>
        pass.shareUrl
            ? [
                  t('guest.ticket.share_all_line', {
                      name: pass.name,
                      url: pass.shareUrl,
                  }),
              ]
            : [],
    );

    if (lines.length < 2) {
        return null;
    }

    const message = [
        t('guest.ticket.share_all_message', { event: eventName }),
        ...lines,
    ].join('\n');

    return (
        <Button asChild className="h-11 w-full">
            <a
                href={`https://wa.me/?text=${encodeURIComponent(message)}`}
                target="_blank"
                rel="noopener noreferrer"
                data-test="ticket-passes-share-all"
            >
                <MessageCircle />
                {t('guest.ticket.share_all_whatsapp')}
            </a>
        </Button>
    );
}
