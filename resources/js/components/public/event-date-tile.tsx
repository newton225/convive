import { useTranslation } from '@/hooks/use-translation';
import { formatDateParts } from '@/lib/format-date';

/**
 * La date d'un evenement en vignette de calendrier : le jour se lit d'un coup d'oeil, le reste
 * suit en toutes lettres.
 */
export function EventDateTile({ startsAt }: { startsAt: string | null }) {
    const { t, locale } = useTranslation();

    if (startsAt === null) {
        return (
            <p className="text-muted-foreground text-sm">
                {t('guest.event.no_date')}
            </p>
        );
    }

    const parts = formatDateParts(startsAt, locale);

    return (
        <div className="flex items-center gap-4">
            <div className="bg-card flex size-16 shrink-0 flex-col items-center justify-center overflow-hidden rounded-2xl text-center">
                <span className="brand-fill w-full py-0.5 text-[0.65rem] font-semibold tracking-wide uppercase">
                    {parts.month}
                </span>
                <span className="flex-1 pt-0.5 text-2xl leading-none font-semibold tabular-nums">
                    {parts.day}
                </span>
            </div>
            <div>
                <p className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                    {t('guest.event.when')}
                </p>
                <p className="font-medium capitalize">{parts.weekday}</p>
                <p className="text-muted-foreground text-sm">{parts.time}</p>
            </div>
        </div>
    );
}
