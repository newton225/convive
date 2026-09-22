<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La paire de cles Ed25519 qui signe les billets de l'evenement (README 2.8, SECURITY.md
     * C2), etape 7 de « Ordre de construction ». Une signature asymetrique et non un HMAC : le
     * scan fonctionne hors ligne, l'appareil de l'agent ne doit jamais detenir de secret, il ne
     * porte que la cle publique de verification.
     *
     * Une paire par evenement, generee paresseusement au premier billet emis
     * (`Event::ensureSigningKeyPair()`), jamais a la creation de l'evenement : la meme raison
     * que `public_token` sur `Event`.
     *
     * `qr_secret_key` est chiffree au repos (voir le cast sur `Event`). Ce n'est pas le KMS ou
     * le HSM que SECURITY.md demande pour la production : c'est un palliatif honnete en
     * attendant cette brique d'infrastructure, pas a reproposer a chaque session (CLAUDE.md,
     * « Paquets »), juste a ne pas oublier au moment du deploiement reel.
     *
     * `qr_key_version` accompagne chaque jeton emis pour permettre une rotation future ; aucun
     * outil de rotation ou de revocation n'est construit ici (CLAUDE.md : pas de mecanique pour
     * un besoin hypothetique), la colonne existe pour ne pas casser le format du jeton le jour
     * ou cet outil sera necessaire.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->text('qr_public_key')->nullable()->after('public_token');
            $table->text('qr_secret_key')->nullable()->after('qr_public_key');
            $table->unsignedInteger('qr_key_version')->default(1)->after('qr_secret_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['qr_public_key', 'qr_secret_key', 'qr_key_version']);
        });
    }
};
