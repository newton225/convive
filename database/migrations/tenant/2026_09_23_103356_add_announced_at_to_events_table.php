<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Annonce sur la vitrine du site produit (CLAUDE.md, « Annonce sur le site produit »,
     * decision du 2026-09-22) : un timestamp, pas un booleen, meme raison que `published_at` -
     * savoir depuis quand l'evenement est annonce importe (tri par recence de la vitrine), et
     * retirer l'annonce se lit comme le vider, pas comme le renverser.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->timestamp('announced_at')->nullable()->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('announced_at');
        });
    }
};
