import {
    flexRender,
    getCoreRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnDef,
    type SortingState,
    type Updater,
} from '@tanstack/react-table';
import { useState } from 'react';
import { DataTableSortHeader } from '@/components/data-table-sort-header';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ListPagination } from '@/components/list-pagination';

export type DataTableMeta = {
    currentPage: number;
    lastPage: number;
    total: number;
};

type ServerProps = {
    // Pagination et tri pilotes par le serveur (`spatie/laravel-query-builder`).
    mode?: 'server';
    meta: DataTableMeta;
    onPageChange: (page: number) => void;
    // Tri serveur facultatif : sans ces deux proprietes, aucune colonne n'est triable.
    sorting?: SortingState;
    onSortingChange?: (sorting: SortingState) => void;
};

type ClientProps = {
    // Tout le jeu de donnees est deja dans la page : tri et pagination dans le navigateur.
    mode: 'client';
    pageSize?: number;
    initialSorting?: SortingState;
};

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
    rowTestId?: string;
} & (ServerProps | ClientProps);

/**
 * Table de donnees partagee du back-office (CLAUDE.md : tri, filtres et pagination viennent de
 * TanStack Table, jamais d'une implementation maison). Deux modes :
 *
 * - `server` (defaut) : la base d'inscrits, le rapprochement, le journal. Le serveur pagine et
 *   trie ; ce composant affiche la page courante et remonte la page ou le tri demandes.
 * - `client` : les listes deja entierement chargees (console d'exploitation). TanStack trie et
 *   pagine dans le navigateur.
 *
 * Une colonne est triable si elle a un accesseur et que le tri n'y est pas coupe
 * (`enableSorting: false`) ; son en-tete texte devient alors un bouton de tri. Recherche et
 * filtres restent a chaque ecran : trop propres a chacun pour etre generiques.
 */
export default function DataTable<TData>(props: Props<TData>) {
    const { columns, data, emptyState, rowTestId } = props;
    const isClient = props.mode === 'client';
    const [clientSorting, setClientSorting] = useState<SortingState>(
        props.mode === 'client' ? (props.initialSorting ?? []) : [],
    );

    const serverSorting = props.mode !== 'client' ? props.sorting : undefined;
    const onServerSortingChange =
        props.mode !== 'client' ? props.onSortingChange : undefined;

    const changeSorting = (updater: Updater<SortingState>) => {
        if (isClient) {
            setClientSorting(updater);

            return;
        }

        const next =
            typeof updater === 'function'
                ? updater(serverSorting ?? [])
                : updater;

        onServerSortingChange?.(next);
    };

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        state: { sorting: isClient ? clientSorting : (serverSorting ?? []) },
        onSortingChange: changeSorting,
        enableSorting: isClient || onServerSortingChange !== undefined,
        // Un seul critere de tri : c'est ce que le serveur sait appliquer, et ce qui se lit.
        enableMultiSort: false,
        // Cote serveur, retirer le tri ramene a l'ordre par defaut du controleur, souvent le meme
        // que le tri affiche (journal par date decroissante) : le clic semblerait sans effet. On
        // alterne donc croissant et decroissant.
        enableSortingRemoval: isClient,
        ...(isClient
            ? {
                  getSortedRowModel: getSortedRowModel(),
                  getPaginationRowModel: getPaginationRowModel(),
                  initialState: {
                      pagination: {
                          pageSize:
                              props.mode === 'client'
                                  ? (props.pageSize ?? 25)
                                  : 25,
                      },
                  },
              }
            : {
                  manualSorting: true,
                  manualPagination: true,
                  pageCount: props.meta.lastPage,
              }),
    });

    const meta: DataTableMeta = isClient
        ? {
              currentPage: table.getState().pagination.pageIndex + 1,
              lastPage: Math.max(1, table.getPageCount()),
              total: data.length,
          }
        : props.meta;

    const goToPage = (page: number) => {
        if (props.mode === 'client') {
            table.setPageIndex(page - 1);
        } else {
            props.onPageChange(page);
        }
    };

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
                                {headerGroup.headers.map((header) => {
                                    const definition =
                                        header.column.columnDef.header;
                                    const sorted = header.column.getIsSorted();

                                    return (
                                        <TableHead
                                            key={header.id}
                                            aria-sort={
                                                sorted === 'asc'
                                                    ? 'ascending'
                                                    : sorted === 'desc'
                                                      ? 'descending'
                                                      : undefined
                                            }
                                        >
                                            {header.isPlaceholder ? null : header.column.getCanSort() &&
                                              typeof definition === 'string' ? (
                                                <DataTableSortHeader
                                                    column={header.column}
                                                    label={definition}
                                                />
                                            ) : (
                                                flexRender(
                                                    definition,
                                                    header.getContext(),
                                                )
                                            )}
                                        </TableHead>
                                    );
                                })}
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

            <ListPagination meta={meta} onPageChange={goToPage} />
        </div>
    );
}
