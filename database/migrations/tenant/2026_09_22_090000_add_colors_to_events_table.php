<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * README ecran 13 : l'assistant de creation propose un visuel et des couleurs propres a
     * l'evenement, distincts de la marque de l'organisation (CLAUDE.md, « Organisation »). Une
     * association qui organise un diner de Noel peut vouloir du vert et du rouge pour cet
     * evenement precis, sans changer la marque de l'organisation qui sert a tous les autres.
     *
     * Colonnes optionnelles : un evenement sans couleur propre retombe sur celles du locataire
     * (`Event::colors()`). Le visuel lui-meme n'a pas de colonne ici : il vit dans `media`
     * (spatie/laravel-medialibrary), comme les fichiers de marque de l'organisation.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('primary_color', 7)->nullable()->after('venue_address');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });
    }
};
