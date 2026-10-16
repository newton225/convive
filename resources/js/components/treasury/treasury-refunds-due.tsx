import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDate } from '@/lib/format-date';
import type { TreasuryRefundDue } from '@/types';

type Props = {
    rows: TreasuryRefundDue[];
    count: number;
    limit: number;
    actions?: React.ReactNode;
};

/**
 * Tout ce qui reste a rembourser dans l'organisation, du plus ancien au plus recent. Le geste de
 * remboursement se fait dans la base d'inscrits de l'evenement : chaque ligne y mene.
 */
export function TreasuryRefundsDue({ rows, count, limit, actions }: Props) {
    const { t, locale } = useTranslation();

    const columns: ColumnDef<TreasuryRefundDue>[] = [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('treasury.refunds.name'),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            id: 'reference',
            accessorKey: 'reference',
            header: t('treasury.refunds.reference'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.reference ?? '-'}
                </span>
            ),
        },
        {
            id: 'event',
            accessorKey: 'eventName',
            header: t('treasury.refunds.event'),
        },
        {
            id: 'amount',
            accessorKey: 'amount',
            header: t('treasury.refunds.amount'),
            cell: ({ row }) => formatAmount(row.original.amount, locale),
        },
        {
            id: 'cancelled_on',
            accessorKey: 'cancelledAt',
            header: t('treasury.refunds.cancelled_on'),
            cell: ({ row }) =>
                row.original.cancelledAt
                    ? formatDate(row.original.cancelledAt, locale)
                    : '-',
        },
        {
            id: 'open',
            header: '',
            enableSorting: false,
            cell: ({ row }) => (
                <Button variant="outline" size="sm" asChild>
                    <Link href={row.original.registrationsUrl}>
                        {t('treasury.refunds.open')}
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <Card data-test="treasury-refunds">
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle className="text-base">
                        {t('treasury.refunds.title')}
                    </CardTitle>
                    <p className="text-muted-foreground text-sm">
                        {t('treasury.refunds.count', { count })}
                    </p>
                </div>
                {actions}
            </CardHeader>
            <CardContent className="space-y-3">
                <DataTable
                    mode="client"
                    columns={columns}
                    data={rows}
                    rowTestId="treasury-refund"
                    emptyState={
                        <p className="text-muted-foreground py-6 text-center text-sm">
                            {t('treasury.refunds.empty')}
                        </p>
                    }
                />
                {count > rows.length ? (
                    <p className="text-muted-foreground text-xs">
                        {t('treasury.refunds.more', { limit })}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}
