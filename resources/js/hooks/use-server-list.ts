import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export type ListFilters = Record<string, string | null | undefined>;

type Changes = {
    filter?: ListFilters;
    // `null` retire le tri : retour a l'ordre par defaut du serveur.
    sort?: string | null;
    page?: number;
};

type Options = {
    url: string;
    filters?: ListFilters;
    sort?: string | null;
    // Valeur d'un filtre qui vaut « aucun filtre » (« all ») : elle ne s'ecrit pas dans l'adresse.
    // `null` quand chaque valeur compte, celle par defaut du serveur n'etant pas « all ».
    neutral?: string | null;
    // Parametre de la page, a changer quand un ecran porte deux listes paginees.
    pageName?: string;
};

/**
 * Une liste paginee par le serveur (decision du 2026-10-07) : la recherche, les filtres, le tri et
 * la page vivent dans l'adresse, si bien qu'ils survivent au bouton retour et se gardent d'une page
 * a l'autre. Changer la recherche ou un filtre ramene a la premiere page. Les parametres d'une
 * autre liste du meme ecran (sa page) sont gardes tels quels.
 */
export function useServerList({
    url,
    filters = {},
    sort,
    neutral = 'all',
    pageName = 'page',
}: Options) {
    const { url: currentUrl } = usePage();
    const [search, setSearch] = useState(filters.search ?? '');

    const visit = (changes: Changes) => {
        const merged = { ...filters, ...changes.filter };
        const filter = Object.fromEntries(
            Object.entries(merged).filter(
                ([, value]) =>
                    value !== null &&
                    value !== undefined &&
                    value !== '' &&
                    (neutral === null || value !== neutral),
            ),
        );
        const nextSort = changes.sort === undefined ? sort : changes.sort;
        const others = Object.fromEntries(
            [...new URL(currentUrl, 'http://localhost').searchParams].filter(
                ([key]) =>
                    !key.startsWith('filter[') &&
                    key !== 'sort' &&
                    key !== pageName,
            ),
        );

        router.get(
            url,
            {
                ...others,
                filter,
                sort: nextSort ?? undefined,
                [pageName]:
                    changes.page !== undefined && changes.page > 1
                        ? changes.page
                        : undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // La derniere version de `visit` (elle lit les filtres du rendu courant), sans relancer la pause
    // de la recherche a chaque rendu.
    const latestVisit = useRef(visit);

    useEffect(() => {
        latestVisit.current = visit;
    });

    const currentSearch = filters.search ?? '';

    // La recherche part apres une courte pause dans la frappe, pas a chaque touche.
    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== currentSearch) {
                latestVisit.current({ filter: { search } });
            }
        }, 300);

        return () => clearTimeout(timeout);
    }, [search, currentSearch]);

    return { search, setSearch, visit };
}
