import type { ColumnDef } from '@tanstack/react-table';
import DataTable from '@/components/data-table';

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
};

/**
 * Les listes de la console arrivent entieres : tri et pagination se font dans le navigateur (mode
 * `client` de `DataTable`). Au dela de quelques milliers de lignes, les listes longues
 * (organisations, journal) devront passer a la pagination et au tri serveur, comme la base
 * d'inscrits (CLAUDE.md, « Composants d'interface »).
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
