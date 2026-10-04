<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verification du telephone par un message WhatsApp de l'invite (decision du proprietaire du
 * projet, 2026-10-04). Table centrale : le message arrive a une adresse unique pour toute la
 * plateforme, qui doit retrouver l'organisation et l'inscription a partir du seul code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_phone_checks', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id');
            $table->string('phone', 32);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'registration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_phone_checks');
    }
};
