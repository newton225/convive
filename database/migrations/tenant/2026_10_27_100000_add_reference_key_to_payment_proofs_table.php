<?php

use App\Models\PaymentProof;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La forme normalisee de la reference de transaction (`PaymentProof::referenceKey()`) : sans espaces,
 * tirets ni ponctuation, en majuscules. Le signal « reference deja utilisee » compare cette forme, pour
 * qu'un code recopie a la main (« ci2410 abc-123 ») retrouve l'original (« CI2410ABC123 »). La reference
 * saisie, elle, reste telle quelle : c'est ce que l'equipe compare au recu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->string('reference_key')->nullable();
            $table->index('reference_key');
        });

        DB::table('payment_proofs')
            ->whereNotNull('reference')
            ->orderBy('id')
            ->chunkById(500, function ($proofs) {
                foreach ($proofs as $proof) {
                    DB::table('payment_proofs')
                        ->where('id', $proof->id)
                        ->update(['reference_key' => PaymentProof::referenceKey($proof->reference)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropIndex(['reference_key']);
            $table->dropColumn('reference_key');
        });
    }
};
