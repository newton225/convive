import type { SortingState } from '@tanstack/react-table';

/**
 * Passage entre l'etat de tri de TanStack Table et le parametre `sort` de
 * `spatie/laravel-query-builder` (`-amount_due` : decroissant sur `amount_due`). L'identifiant de
 * la colonne est le nom du champ que le controleur autorise dans `allowedSorts()`.
 */
export function sortingFromParam(sort: string | null): SortingState {
    if (!sort) {
        return [];
    }

    const desc = sort.startsWith('-');

    return [{ id: desc ? sort.slice(1) : sort, desc }];
}

export function sortingToParam(sorting: SortingState): string | undefined {
    const [first] = sorting;

    return first ? `${first.desc ? '-' : ''}${first.id}` : undefined;
}
