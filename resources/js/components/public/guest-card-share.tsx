import { MessageCircle } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { TicketCardEvent } from '@/types';

type Props = {
    url: string;
    event: TicketCardEvent;
};

/**
 * Retrouver sa propre carte plus tard : l'invite s'envoie le lien de sa carte par WhatsApp (ou le
 * copie), comme il le fait pour le billet d'un accompagnateur. `wa.me` ouvre WhatsApp sans rien
 * envoyer tant que l'invite n'a pas choisi le destinataire. Ce lien rouvre tout le groupe : le texte
 * le dit, il ne se transmet qu'a ceux que l'invite veut faire entrer.
 */
export function GuestCardShare({ url, event }: Props) {
    const { t } = useTranslation();
    const href = `https://wa.me/?text=${encodeURIComponent(
        t('guest.ticket.own_share_message', { event: event.name, url }),
    )}`;

    return (
        <div className="space-y-2" data-test="guest-card-share">
            <div className="grid gap-2">
                <Button
                    asChild
                    size="sm"
                    className="h-auto min-h-11 w-full py-2 whitespace-normal"
                >
                    <a
                        href={href}
                        target="_blank"
                        rel="noopener noreferrer"
                        data-test="guest-card-share-whatsapp"
                    >
                        <MessageCircle />
                        {t('guest.ticket.own_share_whatsapp')}
                    </a>
                </Button>
                <CopyButton
                    value={url}
                    label={t('guest.ticket.copy_link')}
                    className="h-auto min-h-11 w-full py-2 whitespace-normal"
                />
            </div>
            <p className="text-muted-foreground text-center text-xs">
                {t('guest.ticket.own_share_hint')}
            </p>
        </div>
    );
}
