<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Etape 5 de « Ordre de construction » (CLAUDE.md) : la reservation, noyau critique. La
     * table `registrations` existe deja (etape 4) ; ces colonnes lui manquaient pour porter le
     * decompte et la protection contre la survente.
     *
     * `hold_sequence` est le jeton d'unicite reel exige par CLAUDE.md (section Base de donnees) :
     * `lockForUpdate()` est sans effet en SQLite, la garantie contre la survente ne peut donc pas
     * reposer sur lui. Elle repose sur `Cache::lock()` (verrou applicatif, voir
     * `App\Actions\Registrations\HoldRegistration`) plus cette contrainte d'unicite : meme si le
     * verrou echouait a serialiser deux tentatives concurrentes (mauvaise configuration du
     * pilote de cache en production, par exemple), l'une des deux insertions violerait l'unicite
     * de `(event_id, hold_sequence)` et echouerait plutot que de survendre. La valeur n'est
     * jamais reutilisee (pas de recyclage des numeros liberes) : ce n'est pas un numero de siege
     * au sens du plan de salle, juste un jeton anti-collision.
     *
     * `resume_token_hash` corrige un ecart de securite laisse par l'etape 4 : la page de
     * confirmation etait adressee par l'identifiant sequentiel de l'inscription, devinable et
     * enumerable dans une URL publique. CLAUDE.md l'interdit explicitement pour un lien de
     * reprise : jeton aleatoire de 32 octets minimum, stocke hache. Le jeton en clair ne vit
     * jamais en base, seule son empreinte SHA-256 y est comparee (voir
     * `App\Actions\Registrations\CreateRegistration`).
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // Fige a la creation : le nombre de personnes que cette inscription represente
            // (le participant plus ses accompagnateurs), pour calculer la disponibilite sans
            // recompter les accompagnateurs a chaque lecture.
            $table->unsignedTinyInteger('party_size')->default(1)->after('amount_due');

            $table->dateTime('held_until')->nullable()->after('status');
            $table->unsignedInteger('hold_sequence')->nullable()->after('held_until');
            $table->string('resume_token_hash', 64)->nullable()->unique()->after('hold_sequence');

            $table->unique(['event_id', 'hold_sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropUnique(['event_id', 'hold_sequence']);
            $table->dropColumn(['party_size', 'held_until', 'hold_sequence', 'resume_token_hash']);
        });
    }
};
