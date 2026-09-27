import { MessageCircle } from 'lucide-react';
import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { CompanionTicketPass } from '@/types';

type Props = {
    pass: CompanionTicketPass;
    eventName: string;
};

/**
 * Le billet d'un accompagnateur sur la page de l'invite (README 2.8, ecran 7) : son QR, et de quoi
 * lui transmettre son lien individuel s'il arrive seul. Le partage passe par `wa.me`, qui ouvre
 * WhatsApp sans rien envoyer tant que l'invite n'a pas choisi le destinataire.
 */
export function CompanionTicketPassCard({ pass, eventName }: Props) {
    const { t } = useTranslation();
    const shareHref = pass.shareUrl
        ? `https://wa.me/?text=${encodeURIComponent(
              t('guest.ticket.share_message', {
                  event: eventName,
                  url: pass.shareUrl,
              }),
          )}`
        : null;

    return (
        <div
            className="bg-muted/40 space-y-3 rounded-lg p-3"
            data-test="ticket-pass"
        >
            <div className="flex items-center gap-3">
                <img
                    src={pass.qrImage}
                    alt={t('guest.ticket.pass_alt', { name: pass.name })}
                    className="size-24 shrink-0 bg-white"
                />
                <div className="min-w-0">
                    <p className="truncate font-medium">{pass.name}</p>
                    <p className="text-muted-foreground text-sm">{pass.unit}</p>
                </div>
            </div>
            {shareHref && pass.shareUrl ? (
                <div className="flex flex-wrap gap-2">
                    <Button asChild size="sm">
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
