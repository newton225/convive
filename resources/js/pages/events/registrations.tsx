import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useEffect, useState } from 'react';
import DataTable from '@/components/data-table';
import { CancelRegistrationDialog } from '@/components/registrations/cancel-registration-dialog';
import { CancellationsCard } from '@/components/registrations/cancellations-card';
import { InvitationCardDialog } from '@/components/registrations/invitation-card-dialog';
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
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { sortingFromParam, sortingToParam } from '@/lib/server-sorting';
import { index as eventsIndex } from '@/routes/tenants/events';
import { index, purge } from '@/routes/tenants/events/registrations';
import {
    checklists,
    csv,
    excel,
    pdf,
} from '@/routes/tenants/events/registrations/export';
import type {
    RefundChannelOption,
    RegistrationCancellation,
    RegistrationCard,
    RegistrationRow,
    RegistrationsFilters,
    RegistrationsMeta,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    rows: RegistrationRow[];
    meta: RegistrationsMeta;
    filters: RegistrationsFilters;
    cancellations: RegistrationCancellation[];
    refundChannels: RefundChannelOption[];
};

const StatusFilters = [
    'all',
    'confirmed',
    'proof_submitted',
    'without_proof',
    'cancelled',
] as const;

/**
 * README ecran 20 : la base d'inscrits, etape 9 de « Ordre de construction ». Recherche, filtre
 * de statut et pagination passent par le serveur (`spatie/laravel-query-builder`) : la meme
 * requete y produit les exports (Excel/CSV/PDF/listes de controle, a venir dans ce meme ecran).
 */
export default function EventRegistrations({
    tenant,
    event,
    permissions,
    rows,
    meta,
    filters,
    cancellations,
    refundChannels,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');
    const [cancelling, setCancelling] = useState<RegistrationRow | null>(null);
    const [cardFor, setCardFor] = useState<
        (RegistrationRow & { card: RegistrationCard }) | null
    >(null);
    const [purging, setPurging] = useState(false);

    const canCancel = can(permissions, Permission.RegistrationsCancel);
    const canRefund = can(permissions, Permission.RegistrationsRefund);
    const canPurge = can(permissions, Permission.RegistrationsPurge);
    const canExport = can(permissions, Permission.RegistrationsExport);

    // Les exports reprennent le filtre affiche a l'ecran. Un telechargement est un lien simple,
    // pas une visite Inertia (elle ne sait pas afficher un fichier). Les listes de controle
    // ignorent le filtre : elles sont toujours completes.
    const exportQuery = {
        query: {
            'filter[search]': filters.search ?? undefined,
            'filter[status]': filters.status ?? undefined,
            sort: filters.sort ?? undefined,
        },
    };

    const navigate = (params: {
        search?: string;
        status?: string;
        page?: number;
        sort?: string | null;
    }) => {
        router.get(
            index([tenant.slug, event.id]).url,
            {
                filter: {
                    search: params.search ?? filters.search ?? undefined,
                    status:
                        (params.status ?? filters.status ?? 'all') === 'all'
                            ? undefined
                            : (params.status ?? filters.status),
                },
                // `null` retire le tri (retour a l'ordre par defaut du serveur).
                sort:
                    params.sort === undefined
                        ? (filters.sort ?? undefined)
                        : (params.sort ?? undefined),
                page: params.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                navigate({ search });
            }
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    // L'identifiant d'une colonne triable est le champ que le controleur autorise dans
    // `allowedSorts()` : name, party_size, amount_due.
    const columns: ColumnDef<RegistrationRow>[] = [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('registrations.columns.name'),
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    {row.original.reference ? (
                        <p className="text-muted-foreground font-mono text-xs">
                            {row.original.reference}
                        </p>
                    ) : null}
                    <p className="text-muted-foreground text-xs">
                        {row.original.phone}
                    </p>
                </div>
            ),
        },
        {
            header: t('registrations.columns.unit'),
            accessorKey: 'unit',
            enableSorting: false,
        },
        {
            id: 'party_size',
            accessorKey: 'partySize',
            header: t('registrations.columns.party_size'),
        },
        {
            id: 'amount_due',
            accessorKey: 'amountDue',
            header: t('registrations.columns.amount_due'),
            cell: ({ row }) => formatAmount(row.original.amountDue, locale),
        },
        {
            header: t('registrations.columns.status'),
            cell: ({ row }) => (
                <div className="space-y-1">
                    <Badge
                        variant={
                            row.original.status === 'confirmed'
                                ? 'default'
                                : row.original.status === 'cancelled'
                                  ? 'destructive'
                                  : 'secondary'
                        }
                    >
                        {row.original.statusLabel}
                    </Badge>
                    {row.original.cancellationReason ? (
                        <p className="text-muted-foreground text-xs">
                            {row.original.cancellationReason}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            header: t('registrations.columns.table'),
            cell: ({ row }) =>
                row.original.tableNumber ?? t('registrations.actions.no_table'),
        },
        {
            header: t('registrations.columns.channel'),
            cell: ({ row }) => row.original.channelLabel ?? '-',
        },
        {
            header: t('registrations.columns.entry'),
            cell: ({ row }) =>
                row.original.enteredAt ? (
                    <Badge
                        variant={
                            row.original.enteredCount < row.original.partySize
                                ? 'secondary'
                                : 'default'
                        }
                    >
                        {row.original.partySize > 1
                            ? t('registrations.entry.group', {
                                  count: row.original.enteredCount,
                                  total: row.original.partySize,
                                  time: formatDateTime(
                                      row.original.enteredAt,
                                      locale,
                                  ),
                              })
                            : t('registrations.entry.entered', {
                                  time: formatDateTime(
                                      row.original.enteredAt,
                                      locale,
                                  ),
                              })}
                    </Badge>
                ) : (
                    <span className="text-muted-foreground text-xs">
                        {t('registrations.entry.not_yet')}
                    </span>
                ),
        },
        {
            // README 2.7 : savoir si la carte est partie, et quand, sans ouvrir la fiche.
            header: t('registrations.card.column'),
            cell: ({ row }) =>
                row.original.status !== 'confirmed' ? (
                    <span className="text-muted-foreground">-</span>
                ) : row.original.cardSentAt ? (
                    <span
                        className="text-xs"
                        data-test="registration-card-sent"
                    >
                        {t('registrations.card.sent_on', {
                            date: formatDateTime(
                                row.original.cardSentAt,
                                locale,
                            ),
                        })}
                    </span>
                ) : (
                    <span
                        className="text-muted-foreground text-xs"
                        data-test="registration-card-not-sent"
                    >
                        {t('registrations.card.not_sent')}
                    </span>
                ),
        },
        {
            header: t('registrations.columns.actions'),
            cell: ({ row }) => {
                const card = row.original.card;

                return (
                    <div className="flex flex-wrap gap-2">
                        {card !== null ? (
                            <Button
                                variant="outline"
                                size="sm"
                                data-test="registration-card"
                                onClick={() =>
                                    setCardFor({ ...row.original, card })
                                }
                            >
                                {t('registrations.card.open')}
                            </Button>
                        ) : null}
                        {canCancel && row.original.status !== 'cancelled' ? (
                            <Button
                                variant="secondary"
                                size="sm"
                                data-test="registration-cancel"
                                onClick={() => setCancelling(row.original)}
                            >
                                {t('registrations.actions.cancel')}
                            </Button>
                        ) : null}
                    </div>
                );
            },
        },
    ];

    return (
        <>
            <Head title={t('registrations.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('registrations.title')}
                        description={event.name}
                    />

                    {canPurge ? (
                        <Button
                            variant="outline"
                            size="sm"
                            data-test="registrations-purge"
                            onClick={() => setPurging(true)}
                        >
                            {t('registrations.actions.purge')}
                        </Button>
                    ) : null}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t(
                            'registrations.filters.search_placeholder',
                        )}
                        className="max-w-xs"
                        data-test="registrations-search"
                    />

                    <Select
                        value={filters.status ?? 'all'}
                        onValueChange={(status) => navigate({ status })}
                    >
                        <SelectTrigger
                            className="w-56"
                            data-test="registrations-status-filter"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {StatusFilters.map((status) => (
                                <SelectItem key={status} value={status}>
                                    {t(`registrations.filters.${status}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {canExport ? (
                        <div
                            className="flex flex-wrap items-center gap-2 sm:ml-auto"
                            data-test="registrations-exports"
                        >
                            <span className="text-muted-foreground text-sm">
                                {t('registrations.actions.export')}
                            </span>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        excel(
                                            [tenant.slug, event.id],
                                            exportQuery,
                                        ).url
                                    }
                                    data-test="registrations-export-excel"
                                >
                                    {t('registrations.actions.export_excel')}
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        csv(
                                            [tenant.slug, event.id],
                                            exportQuery,
                                        ).url
                                    }
                                    data-test="registrations-export-csv"
                                >
                                    {t('registrations.actions.export_csv')}
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        pdf(
                                            [tenant.slug, event.id],
                                            exportQuery,
                                        ).url
                                    }
                                    data-test="registrations-export-pdf"
                                >
                                    {t('registrations.actions.export_pdf')}
                                </a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a
                                    href={
                                        checklists([tenant.slug, event.id]).url
                                    }
                                    data-test="registrations-export-checklists"
                                >
                                    {t(
                                        'registrations.actions.export_checklists',
                                    )}
                                </a>
                            </Button>
                        </div>
                    ) : null}
                </div>

                <DataTable
                    columns={columns}
                    data={rows}
                    meta={meta}
                    onPageChange={(page) => navigate({ page })}
                    sorting={sortingFromParam(filters.sort)}
                    onSortingChange={(sorting) =>
                        navigate({ sort: sortingToParam(sorting) ?? null })
                    }
                    rowTestId="registration-row"
                    emptyState={
                        <>
                            <p className="font-medium">
                                {t(
                                    `registrations.empty.${filters.status ?? 'all'}.title`,
                                )}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t(
                                    `registrations.empty.${filters.status ?? 'all'}.description`,
                                )}
                            </p>
                        </>
                    }
                />

                <CancellationsCard
                    tenantSlug={tenant.slug}
                    eventId={event.id}
                    cancellations={cancellations}
                    canRefund={canRefund}
                    refundChannels={refundChannels}
                />
            </div>

            {cardFor ? (
                <InvitationCardDialog
                    tenantSlug={tenant.slug}
                    eventId={event.id}
                    registration={cardFor}
                    onClose={() => setCardFor(null)}
                />
            ) : null}

            {cancelling ? (
                <CancelRegistrationDialog
                    tenantSlug={tenant.slug}
                    eventId={event.id}
                    registration={cancelling}
                    canRefund={canRefund}
                    refundChannels={refundChannels}
                    onClose={() => setCancelling(null)}
                />
            ) : null}

            <Dialog open={purging} onOpenChange={setPurging}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('registrations.modals.purge.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('registrations.modals.purge.description')}
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
                            data-test="registrations-purge-confirm"
                            onClick={() =>
                                router.post(purge([tenant.slug, event.id]).url)
                            }
                        >
                            {t('registrations.modals.purge.submit')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventRegistrations.layout = (props: {
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
            title: translate(props.translations, 'registrations.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
