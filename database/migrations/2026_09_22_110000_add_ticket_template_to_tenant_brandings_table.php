<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * README ecran 15 : le gabarit du billet, un reglage d'organisation (pas d'evenement), donc
     * pose sur `tenant_brandings` au meme titre que les couleurs de marque (CLAUDE.md,
     * « Organisation »). `ticket_model` est adosse a `App\Enums\TicketModel`, comme `BrandFile`
     * pour les fichiers de marque.
     */
    public function up(): void
    {
        Schema::table('tenant_brandings', function (Blueprint $table) {
            $table->string('ticket_model')->default('classic');
            $table->boolean('ticket_element_logo')->default(true);
            $table->boolean('ticket_element_stamp')->default(true);
            $table->boolean('ticket_element_signature')->default(true);
            $table->boolean('ticket_element_companions')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_brandings', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_model', 'ticket_element_logo', 'ticket_element_stamp',
                'ticket_element_signature', 'ticket_element_companions',
            ]);
        });
    }
};
