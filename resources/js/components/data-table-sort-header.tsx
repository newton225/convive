import type { Column } from '@tanstack/react-table';
import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

type Props<TData> = {
    column: Column<TData, unknown>;
    label: string;
};

/**
 * L'en-tete d'une colonne triable d'un tableau TanStack : le tri vient de la bibliotheque
 * (`column.getToggleSortingHandler()`), ce composant n'en montre que l'etat. La fleche dit le sens
 * du tri, et le libelle accessible le dit aussi, la forme seule ne suffisant pas.
 */
export function DataTableSortHeader<TData>({ column, label }: Props<TData>) {
    // TanStack Table garde le meme objet `column` d'un rendu a l'autre et change son etat en
    // interne : le React Compiler memoiserait `getIsSorted()` sur cette reference stable, et la
    // fleche resterait figee apres un tri serveur. Le compilateur ecarte deja de lui-meme les
    // composants qui appellent `useReactTable`, pas celui-ci qui ne recoit que la colonne.
    'use no memo';

    const { t } = useTranslation();
    const sorted = column.getIsSorted();
    // L'action du prochain clic depend de la configuration du tableau (tri retirable ou non) :
    // on la demande a TanStack plutot que de la supposer.
    const next = column.getNextSortingOrder();

    return (
        <Button
            type="button"
            variant="ghost"
            size="sm"
            className="-ml-2 h-7 gap-1 px-2"
            onClick={column.getToggleSortingHandler()}
            aria-label={t('common.sort.label', {
                column: label,
                state: t(`common.sort.state.${sorted || 'none'}`),
                action: t(`common.sort.action.${next || 'none'}`),
            })}
        >
            {label}
            {sorted === 'asc' ? (
                <ArrowUp className="size-3.5" />
            ) : sorted === 'desc' ? (
                <ArrowDown className="size-3.5" />
            ) : (
                <ChevronsUpDown className="text-muted-foreground size-3.5" />
            )}
        </Button>
    );
}
