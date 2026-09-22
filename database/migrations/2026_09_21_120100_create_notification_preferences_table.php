<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le canal choisi par un membre pour chaque type d'alerte (README section 5, ecran 25),
     * etape 10. Une ligne seulement quand le membre s'ecarte du canal par defaut.
     *
     * Par utilisateur, pas par organisation : c'est un reglage de la personne, il la suit dans
     * chacun de ses espaces, et une invitation d'equipe (type sans organisation) doit pouvoir
     * etre configuree aussi. `(user_id, type)` unique : un seul choix par type.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('channel');

            $table->timestamps();

            $table->unique(['user_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
