import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFileDownload } from '@/hooks/use-file-download';
import { useTranslation } from '@/hooks/use-translation';
import { table as exportTable } from '@/routes/tenants/treasury/export';
import type { TreasuryFilters } from '@/types';

type Props = {
    tenantSlug: string;
    table: 'events' | 'accounts' | 'refunds';
    filters: TreasuryFilters;
};

/**
 * L'export d'un tableau de la tresorerie en Excel ou en CSV, avec la periode choisie. L'export des
 * remboursements est la liste complete, l'ecran s'arrete a cinquante lignes.
 */
export function TreasuryTableExport({ tenantSlug, table, filters }: Props) {
    const { t } = useTranslation();
    const { downloading, download } = useFileDownload();

    const urlFor = (format: 'excel' | 'csv') =>
        exportTable([tenantSlug, table, format], {
            query: {
                from: filters.from ?? undefined,
                to: filters.to ?? undefined,
            },
        }).url;

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={downloading}
                    data-test={`treasury-export-${table}`}
                >
                    <Download />
                    {t('treasury.export.menu')}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                <DropdownMenuItem
                    onSelect={() => void download(urlFor('excel'))}
                    data-test={`treasury-export-${table}-excel`}
                >
                    {t('treasury.export.excel')}
                </DropdownMenuItem>
                <DropdownMenuItem
                    onSelect={() => void download(urlFor('csv'))}
                    data-test={`treasury-export-${table}-csv`}
                >
                    {t('treasury.export.csv')}
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
