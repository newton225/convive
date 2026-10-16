import { Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDate } from '@/lib/format-date';
import type { TreasuryEventRow } from '@/types';

/**
 * L'argent de chaque evenement de la periode, du plus recent au plus ancien.
 */
export function TreasuryEventsTable({ rows }: { rows: TreasuryEventRow[] }) {
    const { t, locale } = useTranslation();

    const amount = (value: number) => formatAmount(value, locale);

    const columns: ColumnDef<TreasuryEventRow>[] = [
        {
            id: 'name',
            accessorKey: 'name',
            header: t('treasury.events.name'),
            cell: ({ row }) => (
                <Link
                    href={row.original.registrationsUrl}
                    className="font-medium underline-offset-4 hover:underline"
                    title={t('treasury.events.open')}
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            id: 'date',
            accessorKey: 'startsAt',
            header: t('treasury.events.date'),
            cell: ({ row }) =>
                row.original.startsAt
                    ? formatDate(row.original.startsAt, locale)
                    : '-',
        },
        {
            id: 'collected',
            accessorKey: 'collected',
            header: t('treasury.events.collected'),
            cell: ({ row }) => amount(row.original.collected),
        },
        {
            id: 'refunded',
            accessorKey: 'refunded',
            header: t('treasury.events.refunded'),
            cell: ({ row }) => amount(row.original.refunded),
        },
        {
            id: 'fees',
            accessorKey: 'fees',
            header: t('treasury.events.fees'),
            cell: ({ row }) => amount(row.original.fees),
        },
        {
            id: 'due',
            accessorKey: 'due',
            header: t('treasury.events.due'),
            cell: ({ row }) => amount(row.original.due),
        },
        {
            id: 'net',
            accessorKey: 'net',
            header: t('treasury.events.net'),
            cell: ({ row }) => (
                <span className="font-medium">{amount(row.original.net)}</span>
            ),
        },
    ];

    return (
        <Card data-test="treasury-events">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('treasury.events.title')}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <DataTable
                    mode="client"
                    columns={columns}
                    data={rows}
                    rowTestId="treasury-event"
                    emptyState={
                        <p className="text-muted-foreground py-6 text-center text-sm">
                            {t('treasury.events.empty')}
                        </p>
                    }
                />
            </CardContent>
        </Card>
    );
}
