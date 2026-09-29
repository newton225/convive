import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
};

/**
 * PROVISOIRE : les listes de la console arrivent entieres tant qu'elles viennent du jeu
 * d'exemple ; tri et pagination se font donc dans le navigateur (mode `client` de `DataTable`).
 * Quand le serveur arrive, les listes longues (organisations, journal) passent a la pagination et
 * au tri serveur, comme la base d'inscrits.
 */
export function ConsoleTable<TData>({
    columns,
    data,
    emptyState,
}: Props<TData>) {
    return (
        <DataTable
            mode="client"
            columns={columns}
            data={data}
            emptyState={emptyState}
        />
    );
}
