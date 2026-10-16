import {
    flexRender,
    getCoreRowModel,
    getExpandedRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    type ColumnDef,
    type ExpandedState,
    type RowSelectionState,
    type SortingState,
    type Updater,
} from '@tanstack/react-table';
import { ChevronRight } from 'lucide-react';
import { Fragment, useState } from 'react';
import { DataTableSortHeader } from '@/components/data-table-sort-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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

type Selection<TData> = {
    // Les lignes cochees, par identifiant (`getRowId`). Cote serveur la selection ne vaut que pour la
    // page affichee : l'ecran la remet a zero quand les lignes changent.
    selected: RowSelectionState;
    onSelectedChange: (selected: RowSelectionState) => void;
    getRowId: (row: TData) => string;
    // Une ligne qui n'est pas selectionnable n'a pas de case.
    isSelectable: (row: TData) => boolean;
    selectAllLabel: string;
    selectRowLabel: (row: TData) => string;
};

type Expansion<TData> = {
    // Le detail s'ouvre sous la ligne plutot que d'allonger toutes les lignes (comme la file des
    // preuves). Les lignes ouvertes se retiennent par identifiant, donc elles ne suivent pas une
    // autre ligne a la page suivante.
    getRowId: (row: TData) => string;
    // Les lignes ouvertes sont tenues par l'ecran (`useExpandedRows`), qui y ajoute le choix « une
    // seule ligne a la fois » : le meme comportement que la file des preuves.
    expanded: ExpandedState;
    onExpandedChange: (updater: Updater<ExpandedState>) => void;
    renderDetails: (row: TData) => React.ReactNode;
    columnLabel: string;
    showLabel: (row: TData) => string;
    hideLabel: (row: TData) => string;
    detailsTestId?: string;
};

type Props<TData> = {
    columns: ColumnDef<TData, unknown>[];
    data: TData[];
    emptyState: React.ReactNode;
    rowTestId?: string;
    selection?: Selection<TData>;
    expansion?: Expansion<TData>;
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
    const { data, emptyState, rowTestId, selection, expansion } = props;
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

    const withSelection: ColumnDef<TData, unknown>[] = selection
        ? [
              {
                  id: 'select',
                  header: ({ table }) =>
                      table
                          .getCoreRowModel()
                          .rows.some((row) => row.getCanSelect()) ? (
                          <Checkbox
                              checked={
                                  table.getIsAllPageRowsSelected()
                                      ? true
                                      : table.getIsSomePageRowsSelected()
                                        ? 'indeterminate'
                                        : false
                              }
                              onCheckedChange={(value) =>
                                  table.toggleAllPageRowsSelected(
                                      value === true,
                                  )
                              }
                              aria-label={selection.selectAllLabel}
                              data-test="select-all-rows"
                          />
                      ) : null,
                  cell: ({ row }) =>
                      row.getCanSelect() ? (
                          <Checkbox
                              checked={row.getIsSelected()}
                              onCheckedChange={(value) =>
                                  row.toggleSelected(value === true)
                              }
                              aria-label={selection.selectRowLabel(
                                  row.original,
                              )}
                              data-test="select-row"
                          />
                      ) : null,
                  enableSorting: false,
              },
              ...props.columns,
          ]
        : props.columns;

    const columns: ColumnDef<TData, unknown>[] = expansion
        ? [
              {
                  id: 'expand',
                  header: () => (
                      <span className="sr-only">{expansion.columnLabel}</span>
                  ),
                  cell: ({ row }) => (
                      <Button
                          type="button"
                          variant="ghost"
                          size="icon"
                          className="size-8"
                          aria-expanded={row.getIsExpanded()}
                          aria-label={
                              row.getIsExpanded()
                                  ? expansion.hideLabel(row.original)
                                  : expansion.showLabel(row.original)
                          }
                          data-test="row-expand"
                          onClick={row.getToggleExpandedHandler()}
                      >
                          <ChevronRight
                              className={`transition-transform duration-200 ${row.getIsExpanded() ? 'rotate-90' : ''}`}
                          />
                      </Button>
                  ),
                  enableSorting: false,
              },
              ...withSelection,
          ]
        : withSelection;

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
        ...(expansion
            ? {
                  getRowCanExpand: () => true,
                  getExpandedRowModel: getExpandedRowModel(),
                  onExpandedChange: expansion.onExpandedChange,
                  getRowId: (row: TData) => expansion.getRowId(row),
              }
            : {}),
        ...(selection
            ? {
                  getRowId: (row: TData) => selection.getRowId(row),
                  enableRowSelection: (row: { original: TData }) =>
                      selection.isSelectable(row.original),
                  onRowSelectionChange: (updater: Updater<RowSelectionState>) =>
                      selection.onSelectedChange(
                          typeof updater === 'function'
                              ? updater(selection.selected)
                              : updater,
                      ),
              }
            : {}),
        state: {
            sorting: isClient ? clientSorting : (serverSorting ?? []),
            ...(selection ? { rowSelection: selection.selected } : {}),
            ...(expansion ? { expanded: expansion.expanded } : {}),
        },
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
                            <Fragment key={row.id}>
                                <TableRow
                                    data-test={rowTestId}
                                    data-state={
                                        row.getIsExpanded()
                                            ? 'selected'
                                            : undefined
                                    }
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell key={cell.id}>
                                            {flexRender(
                                                cell.column.columnDef.cell,
                                                cell.getContext(),
                                            )}
                                        </TableCell>
                                    ))}
                                </TableRow>
                                {expansion && row.getIsExpanded() ? (
                                    <TableRow
                                        className="hover:bg-transparent"
                                        data-test={expansion.detailsTestId}
                                    >
                                        <TableCell
                                            colSpan={
                                                row.getVisibleCells().length
                                            }
                                            className="bg-muted/40 whitespace-normal"
                                        >
                                            {expansion.renderDetails(
                                                row.original,
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ) : null}
                            </Fragment>
                        ))}
                    </TableBody>
                </Table>
            </div>

            <ListPagination meta={meta} onPageChange={goToPage} />
        </div>
    );
}
