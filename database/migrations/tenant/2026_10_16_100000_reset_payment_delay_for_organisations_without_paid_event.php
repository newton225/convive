<?php

use App\Models\Event;
use Illuminate\Database\Migrations\Migration;

/**
 * Le delai de 24 h des comptes de versement ne part plus que de la premiere publication d'un
 * evenement payant (decision du 2026-10-16) : une organisation qui n'avait publie que du gratuit
 * avait pourtant recu le delai. Elle le perd ici, une seule fois. Une organisation qui a publie un
 * evenement payant, meme efface ensuite de la liste, le garde.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenant = tenant();

        if ($tenant === null || $tenant->first_published_at === null) {
            return;
        }

        if (! Event::withTrashed()->published()->paid()->exists()) {
            $tenant->forceFill(['first_published_at' => null])->saveQuietly();
        }
    }

    public function down(): void
    {
        // Le delai ne se repose pas a l'envers : il repart a la prochaine publication payante.
    }
};
