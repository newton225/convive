import { Head, router } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    useReactTable,
    type ColumnDef,
    type ExpandedState,
    type SortingState,
    type Updater,
} from '@tanstack/react-table';
import {
    ChevronRight,
    Eye,
    Images,
    MessageSquareText,
    TriangleAlert,
} from 'lucide-react';
import { Fragment, useState } from 'react';
import { formatPhoneNumberIntl } from 'react-phone-number-input';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { DataTableSortHeader } from '@/components/data-table-sort-header';
import { DuplicateImageDialog } from '@/components/proofs/duplicate-image-dialog';
import { ReceiptPreviewDialog } from '@/components/proofs/receipt-preview-dialog';
import type { ReceiptPreview } from '@/components/proofs/receipt-preview-dialog';
import { ProofConfirmSummary } from '@/components/proofs/proof-confirm-summary';
import { ProofDetails } from '@/components/proofs/proof-details';
import { ProofsToolbar } from '@/components/proofs/proofs-toolbar';
import Heading from '@/components/heading';
import { ListPagination } from '@/components/list-pagination';
import type { PaginationMeta } from '@/components/list-pagination';
import { ProductTourButton } from '@/components/product-tour-button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLocalPreference } from '@/hooks/use-local-preference';
import { useServerList } from '@/hooks/use-server-list';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime, formatRelative } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import type { ProofSignalFilter } from '@/lib/proof-filters';
import { proofReceiptFacts } from '@/lib/receipt-facts';
import { index as eventsIndex } from '@/routes/tenants/events';
import { approve, index, reject } from '@/routes/tenants/events/proofs';
import type { PaymentProofRow, TenantPermissions, Translations } from '@/types';

// Tris proposes par le serveur (`PaymentProofController::Sorts`) et colonne du tableau de chacun.
const SortColumns: Record<string, string> = {
    submitted_at: 'submittedAt',
    name: 'name',
    amount_due: 'amountDue',
    party_size: 'partySize',
};

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string; hasPassed: boolean };
    permissions: TenantPermissions;
    // La page affichee seulement : recherche, filtre, tri et pagination se font cote serveur.
    rows: PaymentProofRow[];
    meta: PaginationMeta;
    filters: {
        search: string | null;
        signal: ProofSignalFilter;
        sort: string;
    };
    hasProofs: boolean;
};

/**
 * README ecran 18 : la file de verification des preuves, etape 6 de « Ordre de construction ».
 * L'attribution des tables (README 2.6) se declenche a la validation, cote serveur ; rien a
 * afficher ici a ce sujet, l'inscrit confirme apparait ensuite dans le plan de salle.
 */
export default function EventProofs({
    tenant,
    event,
    permissions,
    rows,
    meta,
    filters,
    hasProofs,
}: Props) {
    const { t, locale } = useTranslation();
    const [rejecting, setRejecting] = useState<PaymentProofRow | null>(null);
    const [approving, setApproving] = useState<PaymentProofRow | null>(null);
    const [approveProcessing, setApproveProcessing] = useState(false);
    const [receiptPreview, setReceiptPreview] = useState<ReceiptPreview | null>(
        null,
    );
    const [duplicateImageOf, setDuplicateImageOf] =
        useState<PaymentProofRow | null>(null);
    const [expanded, setExpanded] = useState<ExpandedState>({});
    const { search, setSearch, visit } = useServerList({
        url: index([tenant.slug, event.id]).url,
        filters: { search: filters.search, signal: filters.signal },
        sort: filters.sort,
    });
    const signalFilter = filters.signal;
    const setSignalFilter = (signal: ProofSignalFilter) =>
        visit({ filter: { signal } });
    // Le tri affiche se lit dans l'adresse ; un clic sur un en-tete demande un autre tri au serveur.
    const sorting: SortingState = [
        {
            id: SortColumns[filters.sort.replace(/^-/, '')] ?? 'submittedAt',
            desc: filters.sort.startsWith('-'),
        },
    ];
    const setSorting = (updater: Updater<SortingState>) => {
        const next = typeof updater === 'function' ? updater(sorting) : updater;
        const first = next[0];
        const key = first
            ? Object.keys(SortColumns).find(
                  (candidate) => SortColumns[candidate] === first.id,
              )
            : undefined;

        visit({ sort: key ? (first?.desc ? '-' : '') + key : null });
    };
    const [singleExpand, setSingleExpand] = useLocalPreference(
        'proofs.single-expand',
        false,
    );

    // Une seule ligne ouverte a la fois, si le tresorier l'a choisi : ouvrir une ligne referme
    // les autres. Sinon, TanStack Table garde toutes les lignes ouvertes.
    const changeExpanded = (updater: Updater<ExpandedState>) =>
        setExpanded((previous) => {
            const next =
                typeof updater === 'function' ? updater(previous) : updater;

            if (!singleExpand || next === true) {
                return next;
            }

            const opened = Object.keys(next).filter(
                (id) => next[id] && !(previous !== true && previous[id]),
            );

            return opened.length > 0
                ? { [opened[opened.length - 1]]: true }
                : next;
        });

    const toggleSingleExpand = (checked: boolean) => {
        setSingleExpand(checked);

        // En passant a une seule ligne, on repart d'un tableau replie plutot que de choisir
        // arbitrairement laquelle des lignes deja ouvertes garder.
        if (checked) {
            setExpanded({});
        }
    };

    const canApprove = can(permissions, Permission.ProofsApprove);
    const canReject = can(permissions, Permission.ProofsReject);

    const columns: ColumnDef<PaymentProofRow>[] = [
        {
            id: 'expand',
            enableSorting: false,
            header: () => (
                <span className="sr-only">{t('proofs.details.column')}</span>
            ),
            cell: ({ row }) => (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-8"
                    aria-expanded={row.getIsExpanded()}
                    aria-label={t(
                        row.getIsExpanded()
                            ? 'proofs.details.hide'
                            : 'proofs.details.show',
                        { name: row.original.name },
                    )}
                    data-test="proof-expand"
                    onClick={row.getToggleExpandedHandler()}
                >
                    <ChevronRight
                        className={`transition-transform duration-200 ${row.getIsExpanded() ? 'rotate-90' : ''}`}
                    />
                </Button>
            ),
        },
        {
            id: 'name',
            accessorKey: 'name',
            header: ({ column }) => (
                <DataTableSortHeader
                    column={column}
                    label={t('proofs.columns.name')}
                />
            ),
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    {row.original.registrationReference ? (
                        <p className="text-muted-foreground font-mono text-xs">
                            {row.original.registrationReference}
                        </p>
                    ) : null}
                    <p className="text-muted-foreground text-xs tabular-nums">
                        {formatPhoneNumberIntl(row.original.phone) ||
                            row.original.phone}
                    </p>
                    <p
                        className="text-muted-foreground text-xs"
                        data-test="proof-companion-count"
                    >
                        {t('proofs.companions.count', {
                            count: row.original.companions.length,
                        })}
                    </p>
                </div>
            ),
        },
        {
            accessorKey: 'unit',
            enableSorting: false,
            header: t('proofs.columns.unit'),
        },
        {
            accessorKey: 'partySize',
            header: ({ column }) => (
                <DataTableSortHeader
                    column={column}
                    label={t('proofs.columns.party_size')}
                />
            ),
        },
        {
            accessorKey: 'amountDue',
            header: ({ column }) => (
                <DataTableSortHeader
                    column={column}
                    label={t('proofs.columns.amount_due')}
                />
            ),
            cell: ({ row }) => formatAmount(row.original.amountDue, locale),
        },
        {
            accessorKey: 'submittedAt',
            // Chaine ISO 8601 : l'ordre alphabetique est l'ordre chronologique.
            sortingFn: 'alphanumeric',
            sortUndefined: 'last',
            header: ({ column }) => (
                <DataTableSortHeader
                    column={column}
                    label={t('proofs.columns.submitted_at')}
                />
            ),
            cell: ({ row }) =>
                row.original.submittedAt ? (
                    <div>
                        <p>
                            {formatRelative(row.original.submittedAt, locale)}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {formatDateTime(row.original.submittedAt, locale)}
                        </p>
                    </div>
                ) : null,
        },
        {
            // Le canal decoule du compte choisi (decision du 2026-09-29) : une seule colonne
            // plutot que deux qui repetaient la meme information.
            id: 'payment',
            enableSorting: false,
            header: t('proofs.columns.payment'),
            cell: ({ row }) => (
                <div>
                    <p>{row.original.paymentAccountLabel}</p>
                    <p className="text-muted-foreground text-xs">
                        {row.original.channelLabel}
                        {row.original.reference ? (
                            <>
                                {' · '}
                                <span className="font-mono">
                                    {row.original.reference}
                                </span>
                            </>
                        ) : null}
                    </p>
                </div>
            ),
        },
        {
            id: 'signals',
            enableSorting: false,
            header: t('proofs.columns.signals'),
            cell: ({ row }) => (
                <div
                    className="flex flex-col items-start gap-1.5"
                    data-tour="proof-signals"
                >
                    {row.original.signals.duplicateReference ? (
                        <Badge
                            variant="destructive"
                            data-test="signal-duplicate-reference"
                        >
                            {t('proofs.signals.duplicate_reference')}
                        </Badge>
                    ) : null}
                    {row.original.signals.duplicateImage ? (
                        // Le signal ouvre les versements qui portent la meme capture : le
                        // tresorier les compare sans avoir a les chercher.
                        <Badge variant="destructive" asChild>
                            <button
                                type="button"
                                className="cursor-pointer underline-offset-2 hover:underline"
                                aria-label={t('proofs.duplicate_image.show')}
                                data-test="signal-duplicate-image"
                                onClick={() =>
                                    setDuplicateImageOf(row.original)
                                }
                            >
                                <Images />
                                {t('proofs.signals.duplicate_image')}
                                {row.original.duplicateImageMatches.length >
                                0 ? (
                                    <span className="tabular-nums">
                                        (
                                        {
                                            row.original.duplicateImageMatches
                                                .length
                                        }
                                        )
                                    </span>
                                ) : null}
                            </button>
                        </Badge>
                    ) : null}
                    {row.original.signals.referenceMissingFromStatement ? (
                        <Badge
                            variant="destructive"
                            data-test="signal-reference-missing-from-statement"
                        >
                            {t(
                                'proofs.signals.reference_missing_from_statement',
                            )}
                        </Badge>
                    ) : null}
                    {row.original.signals.statementAmountMismatch ? (
                        <Badge
                            variant="destructive"
                            data-test="signal-statement-amount-mismatch"
                        >
                            {t('proofs.signals.statement_amount_mismatch')}
                        </Badge>
                    ) : null}
                    {row.original.guestNote ? (
                        <Badge
                            variant="secondary"
                            data-test="signal-guest-note"
                        >
                            <MessageSquareText />
                            {t('proofs.signals.guest_note')}
                        </Badge>
                    ) : null}
                    {!Object.values(row.original.signals).some(Boolean) ? (
                        <Badge variant="outline" data-test="signal-none">
                            {t('proofs.signals.none')}
                        </Badge>
                    ) : null}
                </div>
            ),
        },
        {
            id: 'actions',
            enableSorting: false,
            header: t('proofs.columns.actions'),
            cell: ({ row }) => (
                // Boutons compacts : le back-office est plus dense que le parcours invite (CLAUDE.md,
                // « Design »), et trois actions tiennent ainsi sur une ligne sans elargir le tableau.
                <div className="flex items-center justify-end gap-1.5 whitespace-nowrap">
                    {row.original.receiptUrl ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="size-7 [&_svg]:size-3.5"
                                    aria-label={t(
                                        'proofs.actions.open_receipt',
                                    )}
                                    data-test="proof-receipt-link"
                                    data-tour="proof-receipt"
                                    onClick={() =>
                                        setReceiptPreview({
                                            url: row.original.receiptUrl ?? '',
                                            name: row.original.name,
                                            facts: proofReceiptFacts(
                                                row.original,
                                                t,
                                                locale,
                                            ),
                                        })
                                    }
                                >
                                    <Eye />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                {t('proofs.actions.open_receipt')}
                            </TooltipContent>
                        </Tooltip>
                    ) : null}

                    {canReject ? (
                        <Button
                            variant="secondary"
                            size="sm"
                            className="h-7 px-2.5 text-xs"
                            data-test="proof-reject"
                            data-tour="proof-reject"
                            onClick={() => setRejecting(row.original)}
                        >
                            {t('proofs.actions.reject')}
                        </Button>
                    ) : null}

                    {canApprove ? (
                        <Button
                            size="sm"
                            className="h-7 px-2.5 text-xs"
                            data-test="proof-approve"
                            data-tour="proof-approve"
                            onClick={() => setApproving(row.original)}
                        >
                            {t('proofs.actions.approve')}
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    // Recherche, filtre, tri et pagination se font cote serveur (TODO du 2026-10-07, point 11) :
    // TanStack Table n'affiche que la page recue. Lignes depliables : le detail d'une preuve
    // s'ouvre sous sa ligne plutot que d'allonger toutes les lignes.
    const table = useReactTable({
        data: rows,
        columns,
        getRowId: (row) => String(row.proofId),
        state: {
            expanded,
            sorting,
        },
        onExpandedChange: changeExpanded,
        onSortingChange: setSorting,
        getRowCanExpand: () => true,
        getCoreRowModel: getCoreRowModel(),
        getExpandedRowModel: getExpandedRowModel(),
        manualSorting: true,
        manualFiltering: true,
        manualPagination: true,
        pageCount: meta.lastPage,
    });

    const filteredCount = meta.total;

    const resetFilters = () => {
        setSearch('');
        setSignalFilter('all');
    };

    return (
        <>
            <Head title={t('proofs.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('proofs.title')}
                        description={event.name}
                    />
                    <ProductTourButton
                        tour="proofs"
                        autoStart={rows.length > 0}
                    />
                </div>

                {event.hasPassed && hasProofs ? (
                    <Alert data-test="proofs-event-passed">
                        <TriangleAlert />
                        <AlertDescription>
                            {t('proofs.event_passed')}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {!hasProofs ? (
                    <div className="rounded-lg border p-6 text-center">
                        <p className="font-medium">{t('proofs.empty.title')}</p>
                        <p className="text-muted-foreground text-sm">
                            {t('proofs.empty.description')}
                        </p>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <ProofsToolbar
                            search={search}
                            onSearchChange={setSearch}
                            signalFilter={signalFilter}
                            onSignalFilterChange={setSignalFilter}
                            singleExpand={singleExpand}
                            onSingleExpandChange={toggleSingleExpand}
                            count={filteredCount}
                        />
                        {filteredCount === 0 ? (
                            <div
                                className="rounded-lg border p-6 text-center"
                                data-test="proofs-no-match"
                            >
                                <p className="font-medium">
                                    {t('proofs.no_match.title')}
                                </p>
                                <p className="text-muted-foreground text-sm">
                                    {t('proofs.no_match.description')}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    onClick={resetFilters}
                                >
                                    {t('proofs.no_match.reset')}
                                </Button>
                            </div>
                        ) : (
                            <div
                                className="rounded-lg border"
                                data-tour="proof-queue"
                            >
                                <Table>
                                    <TableHeader>
                                        {table
                                            .getHeaderGroups()
                                            .map((headerGroup) => (
                                                <TableRow key={headerGroup.id}>
                                                    {headerGroup.headers.map(
                                                        (header) => (
                                                            <TableHead
                                                                key={header.id}
                                                                aria-sort={
                                                                    header.column.getIsSorted() ===
                                                                    'asc'
                                                                        ? 'ascending'
                                                                        : header.column.getIsSorted() ===
                                                                            'desc'
                                                                          ? 'descending'
                                                                          : undefined
                                                                }
                                                            >
                                                                {header.isPlaceholder
                                                                    ? null
                                                                    : flexRender(
                                                                          header
                                                                              .column
                                                                              .columnDef
                                                                              .header,
                                                                          header.getContext(),
                                                                      )}
                                                            </TableHead>
                                                        ),
                                                    )}
                                                </TableRow>
                                            ))}
                                    </TableHeader>
                                    <TableBody>
                                        {table.getRowModel().rows.map((row) => (
                                            <Fragment key={row.id}>
                                                <TableRow
                                                    data-test="proof-row"
                                                    data-state={
                                                        row.getIsExpanded()
                                                            ? 'selected'
                                                            : undefined
                                                    }
                                                >
                                                    {row
                                                        .getVisibleCells()
                                                        .map((cell) => (
                                                            <TableCell
                                                                key={cell.id}
                                                            >
                                                                {flexRender(
                                                                    cell.column
                                                                        .columnDef
                                                                        .cell,
                                                                    cell.getContext(),
                                                                )}
                                                            </TableCell>
                                                        ))}
                                                </TableRow>
                                                {row.getIsExpanded() ? (
                                                    <TableRow className="hover:bg-transparent">
                                                        <TableCell
                                                            colSpan={
                                                                row.getVisibleCells()
                                                                    .length
                                                            }
                                                            className="bg-muted/40 whitespace-normal"
                                                        >
                                                            <ProofDetails
                                                                proof={
                                                                    row.original
                                                                }
                                                            />
                                                        </TableCell>
                                                    </TableRow>
                                                ) : null}
                                            </Fragment>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}

                        <ListPagination
                            meta={meta}
                            onPageChange={(page) => visit({ page })}
                        />
                    </div>
                )}
            </div>

            <ReceiptPreviewDialog
                receipt={receiptPreview}
                onOpenChange={(open) => !open && setReceiptPreview(null)}
            />

            <DuplicateImageDialog
                proof={duplicateImageOf}
                onOpenChange={(open) => !open && setDuplicateImageOf(null)}
            />

            <ConfirmActionDialog
                open={approving !== null}
                onOpenChange={(open) => !open && setApproving(null)}
                title={t('proofs.actions.approve_confirm_title')}
                description={t('proofs.actions.approve_confirm_description')}
                confirmLabel={t('proofs.actions.approve')}
                processing={approveProcessing}
                testId="proof-approve-confirm"
                onConfirm={() => {
                    if (approving) {
                        router.post(
                            approve([tenant.slug, event.id, approving.proofId])
                                .url,
                            {},
                            {
                                onStart: () => setApproveProcessing(true),
                                onFinish: () => setApproveProcessing(false),
                                onSuccess: () => setApproving(null),
                            },
                        );
                    }
                }}
            >
                {approving ? (
                    <ProofConfirmSummary proof={approving} testId="approve" />
                ) : null}
            </ConfirmActionDialog>

            <Dialog
                open={rejecting !== null}
                onOpenChange={(open) => !open && setRejecting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('proofs.actions.reject_confirm_title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('proofs.actions.reject_confirm_description')}
                        </DialogDescription>
                    </DialogHeader>

                    {/* La precision de l'invite y figure aussi : elle explique souvent l'ecart qui fait douter. */}
                    {rejecting ? (
                        <ProofConfirmSummary
                            proof={rejecting}
                            testId="reject"
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
                            data-test="proof-reject-confirm"
                            onClick={() => {
                                if (rejecting) {
                                    router.post(
                                        reject([
                                            tenant.slug,
                                            event.id,
                                            rejecting.proofId,
                                        ]).url,
                                        {},
                                        { onSuccess: () => setRejecting(null) },
                                    );
                                }
                            }}
                        >
                            {t('proofs.actions.reject')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

EventProofs.layout = (props: {
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
            title: translate(props.translations, 'proofs.title'),
            href: index([props.tenant.slug, props.event.id]),
        },
    ],
});
