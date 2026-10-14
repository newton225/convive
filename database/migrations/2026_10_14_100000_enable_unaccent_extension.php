<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La recherche sans accents (`App\Support\Search\UnaccentedSearch`) appelle `unaccent`, qui est
 * une extension de PostgreSQL a activer base par base. SQLite recoit la fonction du meme nom a
 * l'ouverture de chaque connexion : rien a faire pour lui. Le meme fichier existe cote
 * `migrations/tenant`, pour la base de chaque organisation.
 *
 * L'extension est « de confiance » depuis PostgreSQL 13 : le proprietaire de la base peut
 * l'activer sans etre super-utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            DB::connection($this->getConnection())->statement('create extension if not exists unaccent');
        }
    }

    public function down(): void
    {
        // Laissee en place : d'autres bases peuvent compter dessus, et la retirer casserait la recherche.
    }
};
