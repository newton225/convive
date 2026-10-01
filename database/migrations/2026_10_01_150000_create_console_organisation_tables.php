<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ce que la console tient sur une organisation (README section 3), dans la base centrale.
     *
     * - `tenant_suspensions` : une suspension manuelle, avec son motif obligatoire. Elle est en
     *   cours tant qu'elle n'est pas levee ; la suspension pour impaye reste portee par
     *   l'abonnement.
     * - `tenant_usages` : les compteurs de consommation, releves periodiquement. La console ne
     *   parcourt jamais les bases des organisations a chaque affichage.
     * - `tenants.deletion_scheduled_at` : la date d'effacement d'une organisation dont la
     *   suppression est programmee, annulable jusque-la.
     */
    public function up(): void
    {
        Schema::create('tenant_suspensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->foreignId('suspended_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lifted_at')->nullable();
            $table->foreignId('lifted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'lifted_at']);
        });

        Schema::create('tenant_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('active_events')->default(0);
            $table->unsignedInteger('registrations')->default(0);
            $table->unsignedInteger('members')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('refreshed_at')->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('deletion_scheduled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('deletion_scheduled_at');
        });

        Schema::dropIfExists('tenant_usages');
        Schema::dropIfExists('tenant_suspensions');
    }
};
