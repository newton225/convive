<?php

namespace App\Support\Search;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Str;
use PDO;
use WeakMap;

/**
 * Recherche d'un texte sans tenir compte des accents ni de la casse : « kouame » trouve « Kouamé »,
 * « etat major » trouve « ÉTAT MAJOR ». Toute recherche cote serveur passe par ici (decision du
 * proprietaire du projet, 2026-10-02), jamais par un `like` direct.
 *
 * La comparaison s'ecrit `lower(unaccent(colonne)) like ?`. `unaccent` est une fonction de
 * PostgreSQL (extension du meme nom, a activer par migration le jour du basculement) ; SQLite ne
 * la connait pas, elle lui est donc fournie sous le meme nom a l'ouverture de chaque connexion.
 * C'est le seul SQL ecrit a la main de la recherche (`UnaccentedColumn`) : ni l'un ni l'autre
 * moteur ne sait ignorer les accents avec un `like` ordinaire.
 */
class UnaccentedSearch
{
    /**
     * @var WeakMap<PDO, true>|null
     */
    private static ?WeakMap $prepared = null;

    /**
     * Restrict the query to the rows where one of the columns contains the term.
     *
     * Les conditions sont regroupees : l'appel s'ajoute a la requete comme un seul critere.
     *
     * @template TModel of Model
     *
     * @param  EloquentBuilder<TModel>|QueryBuilder  $query
     * @param  array<int, string>  $columns
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, array $columns, string $term): void
    {
        $needle = self::normalize($term);

        $query->where(function (EloquentBuilder|QueryBuilder $group) use ($columns, $needle) {
            // Rien a chercher (saisie vide, ou faite de seuls jokers) : rien n'est trouve, plutot
            // que tout.
            if ($needle === '') {
                $group->whereRaw('1 = 0');

                return;
            }

            foreach ($columns as $column) {
                $group->orWhere(new UnaccentedColumn($column), 'like', "%{$needle}%");
            }
        });
    }

    /**
     * Get the comparable form of a text : without accents, in lower case.
     *
     * `%` et `\` sont retires : le premier ferait tout trouver, le second est le caractere
     * d'echappement de PostgreSQL et casserait la requete en fin de saisie. `_` reste, il figure
     * dans des adresses email ; il vaut « un caractere quelconque », ce qui trouve aussi le sien.
     */
    public static function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim(str_replace(['%', '\\'], '', $value))));
    }

    /**
     * Give every SQLite connection the `unaccent` function PostgreSQL has natively.
     *
     * Posee avant chaque requete plutot qu'a l'ouverture : une connexion rouverte (reconnexion,
     * base d'une autre organisation) recoit un nouvel objet PDO, qui ne connait plus la fonction.
     */
    public static function prepare(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        $connection->beforeExecuting(function (string $query, array $bindings, Connection $connection): void {
            self::$prepared ??= new WeakMap;

            $pdo = $connection->getPdo();

            if (isset(self::$prepared[$pdo])) {
                return;
            }

            $pdo->sqliteCreateFunction(
                'unaccent',
                fn (mixed $value): ?string => $value === null ? null : Str::ascii((string) $value),
                1,
                PDO::SQLITE_DETERMINISTIC,
            );

            self::$prepared[$pdo] = true;
        });
    }
}
