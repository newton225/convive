import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import DataTable from '@/components/data-table';
import Heading from '@/components/heading';
import type { PaginationMeta } from '@/components/list-pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useServerList } from '@/hooks/use-server-list';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { index as eventsIndex } from '@/routes/tenants/events';
import { index, resolve } from '@/routes/tenants/events/claims';
import type {
    ClaimStatusFilter,
    GuestClaimRow,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    // La page affichee seulement : la liste est paginee par le serveur.
    rows: GuestClaimRow[];
    meta: PaginationMeta;
    filters: { search: string | null; status: ClaimStatusFilter };
};

const StatusFilters: ClaimStatusFilter[] = ['open', 'resolved', 'all'];

/**
 * Les reclamations des invites d'un evenement (decision du 2026-10-09) : qui a ecrit, sur quoi, et le
 * telephone pour le rappeler. L'application ne repond pas : l'organisation rappelle la personne,
 * puis marque la reclamation comme traitee.
 */
export default function EventClaims({
    tenant,
    event,
    permissions,
    rows,
    meta,
    filters,
}: Props) {
    const { t, locale } = useTranslation();
    const [resolving, setResolving] = useState<GuestClaimRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const canResolve = can(permissions, Permission.RegistrationsClaims);
    const { search, setSearch, visit } = useServerList({
        url: index([tenant.slug, event.id]).url,
        filters: { search: filters.search, status: filters.status },
        // « Ouvertes » est le filtre par defaut du serveur : il s'ecrit donc toujours dans l'adresse.
        neutral: null,
    });

    const columns: ColumnDef<GuestClaimRow>[] = [
        {
            id: 'date',
            header: t('claims.columns.date'),
            enableSorting: false,
            cell: ({ row }) =>
                row.original.createdAt
                    ? formatDateTime(row.original.createdAt, locale)
                    : '',
        },
        {
            id: 'guest',
            header: t('claims.columns.guest'),
            enableSorting: false,
            cell: ({ row }) => (
                <div className="space-y-0.5">
                    <p className="font-medium">{row.original.name}</p>
                    <p className="text-muted-foreground text-xs">
                        {row.original.phone}
                    </p>
                    {row.original.reference ? (
                        <p className="text-muted-foreground font-mono text-xs">
                            {row.original.reference}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'category',
            header: t('claims.columns.category'),
            enableSorting: false,
            cell: ({ row }) => t(`claims.categories.${row.original.category}`),
        },
        {
            id: 'message',
            header: t('claims.columns.message'),
            enableSorting: false,
            // Le texte vient d'un inconnu : rendu comme du texte par React, jamais comme du HTML.
            cell: ({ row }) => (
                <p className="max-w-md whitespace-pre-line">
                    {row.original.message}
                </p>
            ),
        },
        {
            id: 'status',
            header: t('claims.columns.status'),
            enableSorting: false,
            cell: ({ row }) =>
                row.original.status === 'open' ? (
                    <Badge variant="secondary">
                        {t('claims.statuses.open')}
                    </Badge>
                ) : (
                    <div className="space-y-0.5">
                        <Badge variant="outline">
                            {t('claims.statuses.resolved')}
                        </Badge>
                        {row.original.resolvedAt ? (
                            <p className="text-muted-foreground text-xs">
                                {t('claims.resolved_on', {
                                    date: formatDateTime(
                                        row.original.resolvedAt,
                                        locale,
                                    ),
                                })}
                            </p>
                        ) : null}
                    </div>
                ),
        },
        {
            id: 'actions',
            header: () => (
                <span className="sr-only">{t('claims.columns.actions')}</span>
            ),
            enableSorting: false,
            cell: ({ row }) =>
                canResolve && row.original.status === 'open' ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setResolving(row.original)}
                        data-test="claim-resolve"
                    >
                        {t('claims.resolve.action')}
                    </Button>
                ) : null,
        },
    ];

    return (
        <>
            <Head title={t('claims.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('claims.title')}
                    description={`${event.name} · ${t('claims.description')}`}
                />

                <div className="flex flex-wrap items-center gap-3">
                    <div className="relative min-w-56 flex-1 sm:max-w-sm">
                        <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(inputEvent) =>
                                setSearch(inputEvent.target.value)
                            }
                            placeholder={t('claims.toolbar.search_placeholder')}
                            aria-label={t('claims.toolbar.search')}
                            className="pl-8"
                            data-test="claims-search"
                        />
                    </div>
                    <Select
                        value={filters.status}
                        onValueChange={(status) =>
                            visit({ filter: { status } })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label={t('claims.filters.status_label')}
                            data-test="claims-status-filter"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {StatusFilters.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {t(`claims.filters.${status}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={rows}
                    meta={meta}
                    onPageChange={(page) => visit({ page })}
                    rowTestId="claim-row"
                    emptyState={
                        <p className="text-muted-foreground text-sm">
                            {filters.status === 'open' &&
                            filters.search === null
                                ? t('claims.empty.open')
                                : t('claims.empty.other')}
                        </p>
                    }
                />
            </div>

            <ConfirmActionDialog
                open={resolving !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setResolving(null);
                    }
                }}
                title={t('claims.resolve.title')}
                description={t('claims.resolve.description', {
                    name: resolving?.name ?? '',
                })}
                confirmLabel={t('claims.resolve.confirm')}
                processing={processing}
                testId="claim-resolve-dialog"
                onConfirm={() => {
                    if (!resolving) {
                        return;
                    }

                    router.post(
                        resolve([tenant.slug, event.id, resolving.id]).url,
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => setProcessing(false),
                            onSuccess: () => setResolving(null),
                        },
                    );
                }}
            />
        </>
    );
}

EventClaims.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'claims.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
