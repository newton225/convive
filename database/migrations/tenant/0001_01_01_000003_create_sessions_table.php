<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les sessions, isolees par locataire comme le cache et les files (CLAUDE.md,
     * « stancl/tenancy », « Bootstrappers ») : `DatabaseTenancyBootstrapper` bascule la connexion
     * par defaut vers celle du locataire des que la tenancy est initialisee, et
     * `session.connection` (`config/session.php`) suit cette connexion par defaut, faute de
     * `SESSION_CONNECTION` explicite. Sans cette table, toute premiere visite du lien public d'un
     * evenement (README ecran 3) echoue en 500 : « no such table: sessions » sur la base du
     * locataire, decouvert le 2026-09-22 en publiant le premier evenement de demonstration.
     *
     * `user_id` sans cle etrangere : `User` vit dans la base centrale (voir CLAUDE.md,
     * « Multi-locataire »), meme raison que `ticket_arrivals.performed_by_user_id`. Un session
     * cote back-office n'y ecrit jamais en pratique (elle demarre sur le domaine central avant
     * l'initialisation de la tenancy), mais la colonne suit le schema par defaut de Laravel.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
