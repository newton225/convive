<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `SendAlert::toTenantMembersGrouped()` cherche l'alerte ouverte par un chemin JSON
 * (`where('data->type', ...)`). SQLite lit ce chemin dans une colonne de texte ; PostgreSQL exige une
 * colonne `json` ou `jsonb` (« l'operateur text ->> unknown n'existe pas »). La colonne passe donc en
 * `jsonb` sur PostgreSQL seulement : le modele la lit et l'ecrit comme avant (`array` en base de
 * donnees de notifications de Laravel), et SQLite garde son texte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->getConnection())->statement('alter table notifications alter column data type jsonb using data::jsonb');
    }

    public function down(): void
    {
        if (DB::connection($this->getConnection())->getDriverName() !== 'pgsql') {
            return;
        }

        DB::connection($this->getConnection())->statement('alter table notifications alter column data type text using data::text');
    }
};
