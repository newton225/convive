<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le releve des taches planifiees (README ecran 31) : une ligne par tache, tenue a jour par le
     * planificateur lui-meme. L'ecran de sante technique y lit quand chacune a tourne pour la
     * derniere fois, si elle a echoue, et si elle est en retard sur sa frequence.
     *
     * Base centrale : les taches balaient toutes les organisations, elles n'appartiennent a aucune.
     */
    public function up(): void
    {
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('expression');
            // Pose a chaque passage du planificateur : une tache retiree du code cesse d'etre vue.
            $table->timestamp('seen_at');
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->unsignedInteger('last_runtime_ms')->nullable();
            $table->timestamp('last_skipped_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->text('last_failure')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
    }
};
