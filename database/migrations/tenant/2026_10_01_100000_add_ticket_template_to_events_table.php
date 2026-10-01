<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le gabarit du billet propre a un evenement (README ecran 15). Tant que
     * `ticket_template_enabled` est faux, celui de l'organisation (`tenant_brandings`) s'applique
     * et les autres colonnes ne sont pas lues ; active, il l'emporte. Le modele reste nul tant que
     * l'evenement n'a jamais eu son propre gabarit.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->boolean('ticket_template_enabled')->default(false);
            $table->string('ticket_model')->nullable();
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
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'ticket_template_enabled', 'ticket_model', 'ticket_element_logo',
                'ticket_element_stamp', 'ticket_element_signature', 'ticket_element_companions',
            ]);
        });
    }
};
