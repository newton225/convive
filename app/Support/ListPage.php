<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Une page de liste, paginee par le serveur (decision du proprietaire du projet, 2026-10-07) : le
 * navigateur ne recoit que la page affichee, et l'adresse garde la recherche et les filtres d'une
 * page a l'autre (`withQueryString()`).
 *
 * Une page demandee au-dela de la derniere (la liste a raccourci depuis, un lien ancien) ramene a la
 * derniere plutot qu'a une page vide qui ferait croire qu'il n'y a plus rien.
 */
final class ListPage
{
    public const PerPage = 25;

    /**
     * Les listes en cartes (evenements, vitrine) : un multiple de 2 et de 3, pour que la grille ne
     * finisse pas sur une ligne incomplete.
     */
    public const CardsPerPage = 24;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    public static function of(Builder $query, Request $request, int $perPage = self::PerPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $total = (clone $query)->toBase()->getCountForPagination();

        return $query
            ->paginate($perPage, ['*'], $pageName, self::page($request, $total, $perPage, $pageName))
            ->withQueryString();
    }

    /**
     * Paginate rows already in memory : a list whose filter cannot run in SQL (the image comparison
     * of the proof queue).
     *
     * @template TItem
     *
     * @param  Collection<int, TItem>  $items
     * @return LengthAwarePaginator<int, TItem>
     */
    public static function ofCollection(Collection $items, Request $request, int $perPage = self::PerPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $page = self::page($request, $items->count(), $perPage, $pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName],
        ))->withQueryString();
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array{currentPage: int, lastPage: int, total: int}
     */
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    private static function page(Request $request, int $total, int $perPage, string $pageName): int
    {
        $last = max(1, (int) ceil($total / $perPage));

        return min(max(1, $request->integer($pageName, 1)), $last);
    }
}
