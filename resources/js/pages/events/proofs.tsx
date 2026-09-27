import { Head, router } from '@inertiajs/react';
import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
} from '@tanstack/react-table';
import { ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { index as eventsIndex } from '@/routes/tenants/events';
import { approve, index, reject } from '@/routes/tenants/events/proofs';
import type { PaymentProofRow, TenantPermissions, Translations } from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    permissions: TenantPermissions;
    rows: PaymentProofRow[];
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
}: Props) {
    const { t, locale } = useTranslation();
    const [rejecting, setRejecting] = useState<PaymentProofRow | null>(null);
    const [approving, setApproving] = useState<PaymentProofRow | null>(null);
    const [approveProcessing, setApproveProcessing] = useState(false);

    const canApprove = can(permissions, Permission.ProofsApprove);
    const canReject = can(permissions, Permission.ProofsReject);

    const columns: ColumnDef<PaymentProofRow>[] = [
        {
            header: t('proofs.columns.name'),
            cell: ({ row }) => (
                <div>
                    <p className="font-medium">{row.original.name}</p>
                    <p className="text-muted-foreground text-xs">
                        {row.original.phone}
                    </p>
                </div>
            ),
        },
        {
            header: t('proofs.columns.unit'),
            accessorKey: 'unit',
        },
        {
            header: t('proofs.columns.party_size'),
            accessorKey: 'partySize',
        },
        {
            header: t('proofs.columns.amount_due'),
            cell: ({ row }) => formatAmount(row.original.amountDue, locale),
        },
        {
            header: t('proofs.columns.submitted_at'),
            cell: ({ row }) =>
                row.original.submittedAt
                    ? formatDateTime(row.original.submittedAt, locale)
                    : null,
        },
        {
            header: t('proofs.columns.channel'),
            cell: ({ row }) => (
                <div>
                    <p>{row.original.channelLabel}</p>
                    {row.original.reference ? (
                        <p className="text-muted-foreground font-mono text-xs">
                            {row.original.reference}
                        </p>
                    ) : null}
                </div>
            ),
        },
        {
            header: t('proofs.columns.amount_declared'),
            cell: ({ row }) =>
                formatAmount(row.original.amountDeclared, locale),
        },
        {
            header: t('proofs.columns.payment_account'),
            accessorKey: 'paymentAccountLabel',
        },
        {
            header: t('proofs.columns.signals'),
            cell: ({ row }) => (
                <div className="flex flex-wrap gap-1">
                    {row.original.signals.duplicateReference ? (
                        <Badge
                            variant="destructive"
                            data-test="signal-duplicate-reference"
                        >
                            {t('proofs.signals.duplicate_reference')}
                        </Badge>
                    ) : null}
                    {row.original.signals.duplicateImage ? (
                        <Badge
                            variant="destructive"
                            data-test="signal-duplicate-image"
                        >
                            {t('proofs.signals.duplicate_image')}
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
                </div>
            ),
        },
        {
            header: t('proofs.columns.actions'),
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center gap-2">
                    {row.original.receiptUrl ? (
                        <Button variant="ghost" size="sm" asChild>
                            <a
                                href={row.original.receiptUrl}
                                target="_blank"
                                rel="noreferrer"
                                data-test="proof-receipt-link"
                            >
                                <ExternalLink />
                                {t('proofs.actions.open_receipt')}
                            </a>
                        </Button>
                    ) : null}

                    {canApprove ? (
                        <Button
                            size="sm"
                            data-test="proof-approve"
                            onClick={() => setApproving(row.original)}
                        >
                            {t('proofs.actions.approve')}
                        </Button>
                    ) : null}

                    {canReject ? (
                        <Button
                            variant="secondary"
                            size="sm"
                            data-test="proof-reject"
                            onClick={() => setRejecting(row.original)}
                        >
                            {t('proofs.actions.reject')}
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    const table = useReactTable({
        data: rows,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    return (
        <>
            <Head title={t('proofs.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('proofs.title')}
                    description={event.name}
                />

                {rows.length === 0 ? (
                    <div className="rounded-lg border p-6 text-center">
                        <p className="font-medium">{t('proofs.empty.title')}</p>
                        <p className="text-muted-foreground text-sm">
                            {t('proofs.empty.description')}
                        </p>
                    </div>
                ) : (
                    <div className="rounded-lg border">
                        <Table>
                            <TableHeader>
                                {table.getHeaderGroups().map((headerGroup) => (
                                    <TableRow key={headerGroup.id}>
                                        {headerGroup.headers.map((header) => (
                                            <TableHead key={header.id}>
                                                {header.isPlaceholder
                                                    ? null
                                                    : flexRender(
                                                          header.column
                                                              .columnDef.header,
                                                          header.getContext(),
                                                      )}
                                            </TableHead>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableHeader>
                            <TableBody>
                                {table.getRowModel().rows.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        data-test="proof-row"
                                    >
                                        {row.getVisibleCells().map((cell) => (
                                            <TableCell key={cell.id}>
                                                {flexRender(
                                                    cell.column.columnDef.cell,
                                                    cell.getContext(),
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

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
                    <dl className="bg-muted grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1 rounded-lg p-3 text-sm">
                        <dt className="text-muted-foreground">
                            {t('proofs.columns.name')}
                        </dt>
                        <dd className="font-medium">{approving.name}</dd>
                        <dt className="text-muted-foreground">
                            {t('proofs.columns.amount_due')}
                        </dt>
                        <dd>{formatAmount(approving.amountDue, locale)}</dd>
                        <dt className="text-muted-foreground">
                            {t('proofs.columns.amount_declared')}
                        </dt>
                        <dd>
                            {formatAmount(approving.amountDeclared, locale)}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('proofs.columns.reference')}
                        </dt>
                        <dd>{approving.reference ?? '-'}</dd>
                    </dl>
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
