import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';
import type { PaginationMeta } from '@/components/list-pagination';

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
    // Liste paginee par le serveur (TODO du 2026-10-07, point 11) : la page affichee et son
    // changement. Sans eux, la liste est courte et arrive entiere.
    meta?: PaginationMeta;
    onPageChange?: (page: number) => void;
};

/**
 * Les listes de la console. Celles qui grandissent (organisations, comptes, journaux) sont paginees
 * par le serveur ; les listes courtes (equipe, plans) arrivent entieres et se trient dans le
 * navigateur (mode `client` de `DataTable`).
 */
export function ConsoleTable<TData>({
    columns,
    data,
    emptyState,
    meta,
    onPageChange,
}: Props<TData>) {
    if (meta && onPageChange) {
        return (
            <DataTable
                columns={columns}
                data={data}
                emptyState={emptyState}
                meta={meta}
                onPageChange={onPageChange}
            />
        );
    }

    return (
        <DataTable
            mode="client"
            columns={columns}
            data={data}
            emptyState={emptyState}
        />
    );
}
