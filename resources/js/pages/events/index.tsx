import { Head, Link, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { EventCard } from '@/components/events/event-card';
import { ConfirmSummary } from '@/components/confirm-summary';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { close, create, index } from '@/routes/tenants/events';
import type {
    EventListItem,
    TenantPermissions,
    TenantSummary,
    Translations,
} from '@/types';

// En cours et a venir d'abord : un evenement termine ne demande plus rien, il reste un clic plus loin.
const EventFilters = ['active', 'closed', 'all'] as const;

type EventFilter = (typeof EventFilters)[number];

type Props = {
    tenant: TenantSummary;
    events: EventListItem[];
    permissions: TenantPermissions;
};

export default function EventsIndex({ tenant, events, permissions }: Props) {
    const { t, locale } = useTranslation();
    const [filter, setFilter] = useState<EventFilter>('active');
    const matches = (event: EventListItem, value: EventFilter) =>
        value === 'all' ||
        (value === 'closed'
            ? event.status === 'closed'
            : event.status !== 'closed');
    const countFor = (value: EventFilter) =>
        events.filter((event) => matches(event, value)).length;
    const visible = events.filter((event) => matches(event, filter));
    const [closing, setClosing] = useState<EventListItem | null>(null);

    return (
        <>
            <Head title={t('events.title')} />

            <h1 className="sr-only">{t('events.title')}</h1>

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('events.title')}
                        description={t('events.description')}
                    />

                    {can(permissions, Permission.EventsCreate) ? (
                        <Button asChild data-test="event-create">
                            <Link href={create(tenant.slug)}>
                                <Plus /> {t('events.actions.create')}
                            </Link>
                        </Button>
                    ) : null}
                </div>

                {events.length === 0 ? (
                    <div className="rounded-xl border border-dashed p-10 text-center">
                        <p className="text-muted-foreground text-sm">
                            {t('events.empty')}
                        </p>
                    </div>
                ) : (
                    <>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            value={filter}
                            onValueChange={(value) => {
                                const next = EventFilters.find(
                                    (candidate) => candidate === value,
                                );

                                if (next) {
                                    setFilter(next);
                                }
                            }}
                            aria-label={t('events.filters.label')}
                            className="w-fit"
                        >
                            {EventFilters.map((value) => (
                                <ToggleGroupItem
                                    key={value}
                                    value={value}
                                    className="px-3"
                                    data-test={`event-filter-${value}`}
                                >
                                    {t(`events.filters.${value}`)}
                                    <span className="text-muted-foreground ml-1 tabular-nums">
                                        {countFor(value)}
                                    </span>
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>

                        {visible.length === 0 ? (
                            <p className="text-muted-foreground text-sm">
                                {t('events.filters.empty')}
                            </p>
                        ) : (
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {visible.map((event) => (
                                    <EventCard
                                        key={event.id}
                                        tenantSlug={tenant.slug}
                                        event={event}
                                        permissions={permissions}
                                        onClose={() => setClosing(event)}
                                    />
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>
            <Dialog
                open={closing !== null}
                onOpenChange={(open) => !open && setClosing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('events.confirm_close.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('events.confirm_close.description', {
                                name: closing?.name ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    {closing ? (
                        <ConfirmSummary
                            testId="event-close-summary"
                            items={[
                                {
                                    label: t('events.fields.name'),
                                    value: closing.name,
                                    emphasis: true,
                                },
                                {
                                    label: t('events.fields.starts_at'),
                                    value: closing.startsAt
                                        ? formatDateTime(
                                              closing.startsAt,
                                              locale,
                                          )
                                        : null,
                                    hidden: closing.startsAt === null,
                                },
                                {
                                    label: t('events.card.fill_label'),
                                    value: t('events.card.fill_value', {
                                        occupied: closing.occupiedSeats,
                                        capacity: closing.capacity,
                                    }),
                                },
                                {
                                    label: t('events.card.collected'),
                                    value: formatAmount(
                                        closing.collectedAmount,
                                        locale,
                                    ),
                                    emphasis: true,
                                },
                                {
                                    // Cloturer avec des preuves en attente laisse sans reponse des
                                    // invites qui ont verse : a voir avant de confirmer.
                                    label: t(
                                        'events.confirm_close.pending_proofs',
                                    ),
                                    value: t('events.card.proofs_to_check', {
                                        count: closing.proofsToCheck,
                                    }),
                                    warning: true,
                                    hidden: closing.proofsToCheck === 0,
                                },
                            ]}
                        />
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            data-test="event-close-confirm"
                            onClick={() => {
                                if (closing) {
                                    router.post(
                                        close([tenant.slug, closing.id]).url,
                                        {},
                                        { onSuccess: () => setClosing(null) },
                                    );
                                }
                            }}
                        >
                            {t('events.actions.close')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventsIndex.layout = (props: {
    tenant: TenantSummary;
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: index(props.tenant.slug),
        },
    ],
});
