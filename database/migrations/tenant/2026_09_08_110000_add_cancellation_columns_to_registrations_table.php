<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etape 9 : `RegistrationStatus::Cancelled` (voir CLAUDE.md, decision produit). `status`
     * lui-meme n'a pas besoin de migration, c'est une colonne `string` libre. `cancelled_by_user_id`
     * n'a pas de cle etrangere : `User` vit dans la base centrale (voir CLAUDE.md,
     * « Multi-locataire »), meme raison que `ScanEvent::performed_by_user_id`.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('proof_reminder_j1_sent_at');
            $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
            $table->unsignedBigInteger('cancelled_by_user_id')->nullable()->after('cancellation_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['cancelled_at', 'cancellation_reason', 'cancelled_by_user_id']);
        });
    }
};
