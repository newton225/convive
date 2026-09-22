import {
    flexRender,
    getCoreRowModel,
    useReactTable,
    type ColumnDef,
} from '@tanstack/react-table';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from '@/hooks/use-translation';

export type DataTableMeta = {
    currentPage: number;
    lastPage: number;
    total: number;
};

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    meta: DataTableMeta;
    onPageChange: (page: number) => void;
    emptyState: React.ReactNode;
    rowTestId?: string;
};

/**
 * Table de donnees partagee par les ecrans qui paginent cote serveur (base d'inscrits, ecran 20,
 * et rapprochement, ecran 19), etape 9. Le tri et la recherche restent hors de ce composant :
 * trop specifiques a chaque ecran pour etre generiques sans sur-abstraction (CLAUDE.md). TanStack
 * Table ne gere ici que le rendu des lignes ; la pagination elle-meme est pilotee par le serveur
 * via `spatie/laravel-query-builder`, ce composant se contente d'afficher la page courante et de
 * declencher `onPageChange`.
 */
export default function DataTable<TData>({
    columns,
    data,
    meta,
    onPageChange,
    emptyState,
    rowTestId,
}: Props<TData>) {
    const { t } = useTranslation();

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        manualPagination: true,
        pageCount: meta.lastPage,
    });

    if (data.length === 0) {
        return (
            <div className="rounded-lg border p-6 text-center">
                {emptyState}
            </div>
        );
    }

    return (
        <div className="space-y-3">
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
                                                  header.column.columnDef
                                                      .header,
                                                  header.getContext(),
                                              )}
                                    </TableHead>
                                ))}
                            </TableRow>
                        ))}
                    </TableHeader>
                    <TableBody>
                        {table.getRowModel().rows.map((row) => (
                            <TableRow key={row.id} data-test={rowTestId}>
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

            {meta.lastPage > 1 ? (
                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {t('common.pagination.page_of', {
                            current: String(meta.currentPage),
                            last: String(meta.lastPage),
                        })}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.currentPage <= 1}
                            onClick={() => onPageChange(meta.currentPage - 1)}
                        >
                            {t('common.pagination.previous')}
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={meta.currentPage >= meta.lastPage}
                            onClick={() => onPageChange(meta.currentPage + 1)}
                        >
                            {t('common.pagination.next')}
                        </Button>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
