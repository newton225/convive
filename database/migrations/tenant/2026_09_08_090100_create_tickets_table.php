<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le billet d'une inscription confirmee (README 2.8, ecran 7), etape 7 de « Ordre de
     * construction ». Une ligne par inscription : `registration_id` unique, meme choix que
     * `registration_table_assignments`, `unique()` avant `constrained()`.
     *
     * `nonce` est la part aleatoire du jeton QR signe (voir `App\Support\TicketToken`) : le
     * jeton lui-meme n'est pas stocke, il se recalcule a la demande a partir du nonce et de la
     * cle de l'evenement. Stocker le nonce permet de verifier qu'un jeton presente au scan
     * correspond bien a un billet reellement emis, en plus de la signature elle-meme (defense
     * en profondeur, README 2.8).
     */
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nonce', 64)->unique();
            $table->unsignedInteger('key_version');
            $table->timestamp('issued_at');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
