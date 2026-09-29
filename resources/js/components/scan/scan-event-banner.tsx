import { Link } from '@inertiajs/react';
import { ArrowLeftRight, MapPin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateParts } from '@/lib/format-date';
import { entryControl } from '@/routes/tenants';
import type { ScanEventProps, ScanSameDayEvent } from '@/types';

type Props = {
    tenantSlug: string;
    event: ScanEventProps;
    otherEventsToday: ScanSameDayEvent[];
};

/**
 * L'evenement controle, en tete de l'ecran de scan : nom, lieu et heure. Deux soirees le meme jour
 * portent souvent le meme nom, le lieu dit a l'agent sur quel controle il se trouve. Quand d'autres
 * controles ont lieu aujourd'hui, ils sont nommes et l'agent peut changer d'evenement.
 */
export function ScanEventBanner({
    tenantSlug,
    event,
    otherEventsToday,
}: Props) {
    const { t, locale } = useTranslation();
    const time = event.startsAt
        ? formatDateParts(event.startsAt, locale).time
        : null;

    const others = otherEventsToday
        .map((other) =>
            [
                other.venue ?? other.name,
                other.startsAt
                    ? formatDateParts(other.startsAt, locale).time
                    : null,
            ]
                .filter(Boolean)
                .join(' '),
        )
        .join(', ');

    return (
        <div
            className="bg-card space-y-3 rounded-xl p-4"
            data-test="scan-event-banner"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-lg leading-tight font-semibold">
                        {event.name}
                    </p>
                    {event.venue || time ? (
                        <p className="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                            <MapPin className="size-4 shrink-0" />
                            {[event.venue, time].filter(Boolean).join(' · ')}
                        </p>
                    ) : null}
                </div>
                {otherEventsToday.length > 0 ? (
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={entryControl(tenantSlug, {
                                query: { choose: 1 },
                            })}
                            data-test="scan-change-event"
                        >
                            <ArrowLeftRight />
                            {t('scan.entry_control.change')}
                        </Link>
                    </Button>
                ) : null}
            </div>
            {otherEventsToday.length > 0 ? (
                <p className="text-muted-foreground text-xs">
                    {t('scan.entry_control.others_today', { events: others })}
                </p>
            ) : null}
        </div>
    );
}
