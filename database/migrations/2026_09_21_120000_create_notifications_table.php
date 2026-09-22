<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les alertes de l'application (README section 5), etape 10 de « Ordre de construction ».
     *
     * Table de la base centrale : un utilisateur appartient a plusieurs organisations, sa cloche
     * doit les rassembler sans changer de base. Le modele `App\Models\DatabaseNotification` force
     * cette connexion meme quand une tenancy est active.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
