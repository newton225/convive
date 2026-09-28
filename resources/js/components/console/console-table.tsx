import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
};

/**
 * PROVISOIRE : les listes de la console tiennent sur une page tant qu'elles viennent du jeu
 * d'exemple. Quand le serveur arrive, les listes longues (organisations, journal) passent a la
 * pagination serveur de `DataTable`, comme la base d'inscrits.
 */
export function ConsoleTable<TData>({
    columns,
    data,
    emptyState,
}: Props<TData>) {
    return (
        <DataTable
            columns={columns}
            data={data}
            meta={{ currentPage: 1, lastPage: 1, total: data.length }}
            onPageChange={() => undefined}
            emptyState={emptyState}
        />
    );
}
