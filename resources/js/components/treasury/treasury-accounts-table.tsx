import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { TreasuryAccountRow } from '@/types';

/**
 * L'argent de chaque compte de versement : ce que ses preuves ont apporte, rendu et a rendre. Sert a
 * verifier, compte par compte, que l'argent annonce est bien celui qui est arrive.
 */
export function TreasuryAccountsTable({
    rows,
    actions,
}: {
    rows: TreasuryAccountRow[];
    actions?: React.ReactNode;
}) {
    const { t, locale } = useTranslation();

    const amount = (value: number) => formatAmount(value, locale);

    const columns: ColumnDef<TreasuryAccountRow>[] = [
        {
            id: 'label',
            accessorKey: 'label',
            header: t('treasury.accounts.label'),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.label}</span>
            ),
        },
        {
            id: 'channel',
            accessorKey: 'channelLabel',
            header: t('treasury.accounts.channel'),
            cell: ({ row }) => row.original.channelLabel ?? '-',
        },
        {
            id: 'collected',
            accessorKey: 'collected',
            header: t('treasury.accounts.collected'),
            cell: ({ row }) => amount(row.original.collected),
        },
        {
            id: 'refunded',
            accessorKey: 'refunded',
            header: t('treasury.accounts.refunded'),
            cell: ({ row }) => amount(row.original.refunded),
        },
        {
            id: 'due',
            accessorKey: 'due',
            header: t('treasury.accounts.due'),
            cell: ({ row }) => amount(row.original.due),
        },
    ];

    return (
        <Card data-test="treasury-accounts">
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="space-y-1.5">
                    <CardTitle className="text-base">
                        {t('treasury.accounts.title')}
                    </CardTitle>
                    <p className="text-muted-foreground text-sm">
                        {t('treasury.accounts.description')}
                    </p>
                </div>
                {actions}
            </CardHeader>
            <CardContent>
                <DataTable
                    mode="client"
                    columns={columns}
                    data={rows}
                    rowTestId="treasury-account"
                    emptyState={
                        <p className="text-muted-foreground py-6 text-center text-sm">
                            {t('treasury.accounts.empty')}
                        </p>
                    }
                />
            </CardContent>
        </Card>
    );
}
