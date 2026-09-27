import { Head, Link } from '@inertiajs/react';
import { ScanLine } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { entryControl } from '@/routes/tenants';
import { index as eventsIndex } from '@/routes/tenants/events';
import { index as scanIndex } from '@/routes/tenants/events/scan';
import type { Translations } from '@/types';

type EntryControlEvent = {
    id: number;
    name: string;
    startsAt: string | null;
    venue: string | null;
    statusLabel: string;
};

type Props = {
    tenant: { slug: string };
    events: EntryControlEvent[];
};

/**
 * Le choix de l'evenement a controler (README ecran 26), atteint par le raccourci du menu quand
 * aucun ou plusieurs evenements ont lieu aujourd'hui. Un seul evenement redirige directement.
 */
export default function EntryControl({ tenant, events }: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('scan.entry_control.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('scan.entry_control.title')}
                    description={t('scan.entry_control.description')}
                />

                {events.length === 0 ? (
                    <div
                        className="space-y-3 rounded-lg border border-dashed p-6 text-center"
                        data-test="entry-control-empty"
                    >
                        <p className="font-medium">
                            {t('scan.entry_control.empty_title')}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {t('scan.entry_control.empty_description')}
                        </p>
                        <Button asChild variant="outline">
                            <Link href={eventsIndex(tenant.slug)}>
                                {t('scan.entry_control.all_events')}
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="space-y-3">
                        {events.map((event) => (
                            <div
                                key={event.id}
                                data-test="entry-control-event"
                                className="flex flex-wrap items-center justify-between gap-4 rounded-lg border p-4"
                            >
                                <div className="min-w-0 space-y-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-medium">
                                            {event.name}
                                        </span>
                                        <Badge variant="secondary">
                                            {event.statusLabel}
                                        </Badge>
                                    </div>
                                    <p className="text-muted-foreground text-sm">
                                        {event.startsAt
                                            ? formatDateTime(
                                                  event.startsAt,
                                                  locale,
                                              )
                                            : null}
                                        {event.venue ? ` · ${event.venue}` : ''}
                                    </p>
                                </div>
                                <Button asChild>
                                    <Link
                                        href={scanIndex([
                                            tenant.slug,
                                            event.id,
                                        ])}
                                    >
                                        <ScanLine />
                                        {t('scan.entry_control.open')}
                                    </Link>
                                </Button>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

EntryControl.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'scan.entry_control.title'),
            href: entryControl(props.tenant.slug),
        },
    ],
});
