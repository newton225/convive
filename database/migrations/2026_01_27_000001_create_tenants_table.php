<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_personal')->default(false);

            // Bookkeeping interne de stancl/tenancy (prefixe tenancy_) : le nom de base
            // effectivement attribue, et des identifiants de connexion si un jour un moteur
            // en exige (MySQL/Postgres). Sans objet en SQLite, ou le nom de base se recalcule
            // sans surprise depuis l'identifiant, mais le paquet les ecrit inconditionnellement
            // a la creation.
            $table->string('tenancy_db_name')->nullable();
            $table->string('tenancy_db_username')->nullable();
            $table->string('tenancy_db_password')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        // L'appartenance seule vit ici, dans la base centrale. Le profil porte par le membre
        // est une affectation spatie/laravel-permission dans la base du locataire : une seule
        // source de verite, mais qui n'est plus la meme base que cette table.
        Schema::create('tenant_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            // Pas de contrainte de cle etrangere : `profiles` vit desormais dans la base du
            // locataire, une base physiquement separee de celle-ci. L'integrite est verifiee
            // cote applicatif, a la creation de l'invitation et a son acceptation. Le nom est
            // duplique ici : afficher une invitation (liste, email) ne doit pas exiger de
            // rejoindre la base du locataire pour lire le profil qu'elle offre.
            $table->unsignedBigInteger('profile_id');
            $table->string('profile_name');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
        Schema::dropIfExists('tenant_members');
        Schema::dropIfExists('tenants');
    }
};
