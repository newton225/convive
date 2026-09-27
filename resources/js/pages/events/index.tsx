import { Head, Link, router } from '@inertiajs/react';
import { Copy, Plus } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { close, create, duplicate, edit, index } from '@/routes/tenants/events';
import { index as proofsIndex } from '@/routes/tenants/events/proofs';
import { index as reconciliationIndex } from '@/routes/tenants/events/reconciliation';
import { edit as settingsEdit } from '@/routes/tenants/events/settings';
import { index as registrationsIndex } from '@/routes/tenants/events/registrations';
import { show as reportShow } from '@/routes/tenants/events/report';
import { index as scanIndex } from '@/routes/tenants/events/scan';
import { index as seatingIndex } from '@/routes/tenants/events/seating';
import type {
    EventSummary,
    TenantPermissions,
    TenantSummary,
    Translations,
} from '@/types';

type Props = {
    tenant: TenantSummary;
    events: EventSummary[];
    permissions: TenantPermissions;
};

export default function EventsIndex({ tenant, events, permissions }: Props) {
    const { t, locale } = useTranslation();
    const [closing, setClosing] = useState<EventSummary | null>(null);

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
                    <p className="text-muted-foreground text-sm">
                        {t('events.empty')}
                    </p>
                ) : null}

                <div className="space-y-3">
                    {events.map((event) => (
                        <div
                            key={event.id}
                            data-test="event-row"
                            className="flex flex-wrap items-start justify-between gap-4 rounded-lg border p-4"
                        >
                            <div className="min-w-0 space-y-1">
                                <div className="flex flex-wrap items-center gap-2">
                                    <Link
                                        href={edit([tenant.slug, event.id])}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {event.name}
                                    </Link>
                                    <Badge variant="secondary">
                                        {event.statusLabel}
                                    </Badge>
                                    {event.isPublished ? (
                                        <Badge variant="outline">
                                            {t('events.badges.published')}
                                        </Badge>
                                    ) : null}
                                    {!event.isPublished &&
                                    !event.isReadyToPublish ? (
                                        <Badge variant="outline">
                                            {t('events.badges.not_ready')}
                                        </Badge>
                                    ) : null}
                                </div>

                                <p className="text-muted-foreground text-sm">
                                    {event.startsAt
                                        ? formatDateTime(event.startsAt, locale)
                                        : t('events.summary.no_date')}
                                    {event.venue ? ` · ${event.venue}` : ''}
                                    {' · '}
                                    {t('events.summary.capacity', {
                                        count: event.capacity,
                                    })}
                                </p>

                                {event.publicUrl ? (
                                    <p className="text-muted-foreground flex items-center gap-2 text-xs">
                                        <span className="truncate">
                                            {event.publicUrl}
                                        </span>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            data-test="event-copy-link"
                                            onClick={() =>
                                                navigator.clipboard?.writeText(
                                                    event.publicUrl ?? '',
                                                )
                                            }
                                        >
                                            <Copy className="h-3 w-3" />
                                            {t('events.actions.copy_link')}
                                        </Button>
                                    </p>
                                ) : null}
                            </div>

                            <div className="flex shrink-0 flex-wrap items-center gap-2">
                                {can(
                                    permissions,
                                    Permission.RegistrationsView,
                                ) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-registrations"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                registrationsIndex([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.registrations')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.ProofsView) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-proofs"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                proofsIndex([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.proofs')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.SeatingView) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-seating"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                seatingIndex([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.seating')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(
                                    permissions,
                                    Permission.ReconciliationImport,
                                ) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-reconciliation"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                reconciliationIndex([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.reconciliation')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.ReportsView) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-report"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                reportShow([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.report')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.EventsUpdate) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-settings"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                settingsEdit([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.settings.link')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.ScanPerform) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-scan"
                                        asChild
                                    >
                                        <Link
                                            href={
                                                scanIndex([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url
                                            }
                                        >
                                            {t('events.actions.scan')}
                                        </Link>
                                    </Button>
                                ) : null}

                                {can(
                                    permissions,
                                    Permission.EventsDuplicate,
                                ) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="event-duplicate"
                                        onClick={() =>
                                            router.post(
                                                duplicate([
                                                    tenant.slug,
                                                    event.id,
                                                ]).url,
                                            )
                                        }
                                    >
                                        {t('events.actions.duplicate')}
                                    </Button>
                                ) : null}

                                {can(permissions, Permission.EventsClose) &&
                                event.status !== 'closed' ? (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        data-test="event-close"
                                        onClick={() => setClosing(event)}
                                    >
                                        {t('events.actions.close')}
                                    </Button>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
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
