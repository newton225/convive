import { Link } from '@inertiajs/react';
import { CalendarDays, ImageOff, MapPin } from 'lucide-react';
import { EventActionsMenu } from '@/components/events/event-actions-menu';
import { EventFillBar } from '@/components/events/event-fill-bar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { edit } from '@/routes/tenants/events';
import { index as proofsIndex } from '@/routes/tenants/events/proofs';
import { show as reportShow } from '@/routes/tenants/events/report';
import { index as scanIndex } from '@/routes/tenants/events/scan';
import type { EventListItem, TenantPermissions } from '@/types';

type Props = {
    tenantSlug: string;
    event: EventListItem;
    permissions: TenantPermissions;
    onClose: () => void;
};

type PrimaryAction = { href: string; label: string };

/**
 * Une carte de « Mes evenements » (README ecran 12) : visuel, statut, date, remplissage, montant
 * collecte, preuves en attente, et une seule action mise en avant, celle que le moment appelle.
 * Le reste des destinations est dans le menu « ... ».
 */
export function EventCard({ tenantSlug, event, permissions, onClose }: Props) {
    const { t, locale } = useTranslation();
    const target: [string, number] = [tenantSlug, event.id];

    const primary = ((): PrimaryAction => {
        if (event.status === 'draft') {
            return {
                href: edit(target).url,
                label: t('events.card.continue'),
            };
        }

        if (
            event.status === 'closed' &&
            can(permissions, Permission.ReportsView)
        ) {
            return {
                href: reportShow(target).url,
                label: t('events.card.view_report'),
            };
        }

        if (
            event.status === 'ongoing' &&
            can(permissions, Permission.ScanPerform)
        ) {
            return {
                href: scanIndex(target).url,
                label: t('events.card.open_scan'),
            };
        }

        if (
            event.proofsToCheck > 0 &&
            can(permissions, Permission.ProofsView)
        ) {
            return {
                href: proofsIndex(target).url,
                label: t('events.card.check_proofs', {
                    count: event.proofsToCheck,
                }),
            };
        }

        return { href: edit(target).url, label: t('events.card.open') };
    })();

    return (
        <article
            className="bg-card flex flex-col overflow-hidden rounded-xl border"
            data-test="event-row"
        >
            <Link href={edit(target).url} tabIndex={-1} aria-hidden="true">
                {event.visualUrl ? (
                    <img
                        src={event.visualUrl}
                        alt=""
                        className="aspect-[5/2] w-full object-cover"
                        loading="lazy"
                    />
                ) : (
                    <div className="bg-muted text-muted-foreground flex aspect-[5/2] w-full items-center justify-center">
                        <ImageOff className="size-6" />
                    </div>
                )}
            </Link>

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="space-y-2">
                    <div className="flex items-start justify-between gap-2">
                        <Link
                            href={edit(target).url}
                            className="line-clamp-2 font-medium underline-offset-4 hover:underline"
                        >
                            {event.name}
                        </Link>
                        <EventActionsMenu
                            tenantSlug={tenantSlug}
                            event={event}
                            permissions={permissions}
                            onClose={onClose}
                        />
                    </div>

                    <div className="flex flex-wrap gap-1.5">
                        <Badge variant="secondary">{event.statusLabel}</Badge>
                        {!event.isPublished && !event.isReadyToPublish ? (
                            <Badge variant="outline">
                                {t('events.badges.not_ready')}
                            </Badge>
                        ) : null}
                        {event.isAnnounced ? (
                            <Badge variant="outline">
                                {t('events.badges.announced')}
                            </Badge>
                        ) : null}
                    </div>

                    <div className="text-muted-foreground space-y-1 text-sm">
                        <p className="flex items-center gap-2">
                            <CalendarDays className="size-4 shrink-0" />
                            {event.startsAt
                                ? formatDateTime(event.startsAt, locale)
                                : t('events.summary.no_date')}
                        </p>
                        {event.venue ? (
                            <p className="flex items-center gap-2">
                                <MapPin className="size-4 shrink-0" />
                                <span className="truncate">{event.venue}</span>
                            </p>
                        ) : null}
                    </div>
                </div>

                {event.status !== 'draft' ? (
                    <div className="space-y-3">
                        <EventFillBar
                            occupied={event.occupiedSeats}
                            capacity={event.capacity}
                        />
                        <div className="flex items-baseline justify-between text-xs">
                            <span className="text-muted-foreground">
                                {t('events.card.collected')}
                            </span>
                            <span className="font-medium tabular-nums">
                                {formatAmount(event.collectedAmount, locale)}
                            </span>
                        </div>
                        {event.proofsToCheck > 0 ? (
                            <p
                                className="text-sm font-medium"
                                data-test="event-proofs-to-check"
                            >
                                {t('events.card.proofs_to_check', {
                                    count: event.proofsToCheck,
                                })}
                            </p>
                        ) : null}
                    </div>
                ) : (
                    <p className="text-muted-foreground text-sm">
                        {t('events.card.draft_hint')}
                    </p>
                )}

                <Button
                    asChild
                    variant="outline"
                    className="mt-auto w-full"
                    data-test="event-primary-action"
                >
                    <Link href={primary.href}>{primary.label}</Link>
                </Button>
            </div>
        </article>
    );
}
