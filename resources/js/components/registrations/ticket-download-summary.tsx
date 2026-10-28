import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { TicketDownloadStats } from '@/types';

type Props = {
    summary: TicketDownloadStats;
    onShowNever: () => void;
};

/**
 * Combien d'inscrits confirmes ont recupere leur billet, et combien ne l'ont jamais fait (decision du
 * 2026-10-28). Tout l'evenement, pas le filtre actif : c'est un chiffre de suivi avant le jour J. Le bouton
 * ouvre la liste de ceux a relancer.
 */
export function TicketDownloadSummary({ summary, onShowNever }: Props) {
    const { t } = useTranslation();

    if (summary.confirmed === 0) {
        return null;
    }

    return (
        <div
            className="text-muted-foreground flex flex-wrap items-center gap-x-3 gap-y-1 text-sm"
            data-test="ticket-download-summary"
        >
            <span>
                {t('registrations.ticket_summary.text', {
                    downloaded: summary.downloaded,
                    confirmed: summary.confirmed,
                })}
            </span>
            {summary.never > 0 ? (
                <Button
                    type="button"
                    variant="link"
                    size="sm"
                    className="h-auto p-0"
                    onClick={onShowNever}
                    data-test="ticket-never-show"
                >
                    {t('registrations.ticket_summary.never', {
                        count: summary.never,
                    })}
                </Button>
            ) : null}
        </div>
    );
}
