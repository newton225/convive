<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Plusieurs tarifs par evenement, nommes par l'organisateur (decision du proprietaire du projet,
     * 2026-10-07) : chaque personne inscrite, accompagnateurs compris, choisit sa categorie ; le
     * montant du est la somme des tarifs choisis. Un quota facultatif limite une categorie, dans la
     * capacite de la salle.
     *
     * Chaque evenement existant recoit une categorie « Tarif unique » a son ancien prix, et ses
     * inscriptions y sont rattachees. `events.price_per_person` reste, tenu au tarif le plus bas
     * (« a partir de ») : aucune colonne n'est retiree sans accord.
     */
    public function up(): void
    {
        Schema::create('event_price_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->unsignedInteger('price');
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['event_id', 'name']);
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->foreignId('price_category_id')->nullable()->constrained('event_price_categories')->nullOnDelete();
        });

        Schema::table('registration_companions', function (Blueprint $table) {
            $table->foreignId('price_category_id')->nullable()->constrained('event_price_categories')->nullOnDelete();
        });

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignId('price_category_id')->nullable()->constrained('event_price_categories')->nullOnDelete();
        });

        $now = now()->format('Y-m-d H:i:s');

        foreach (DB::table('events')->get(['id', 'price_per_person']) as $event) {
            $categoryId = DB::table('event_price_categories')->insertGetId([
                'event_id' => $event->id,
                'name' => 'Tarif unique',
                'price' => (int) $event->price_per_person,
                'quota' => null,
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $registrationIds = DB::table('registrations')->where('event_id', $event->id)->pluck('id');

            DB::table('registrations')->whereIn('id', $registrationIds)->update(['price_category_id' => $categoryId]);
            DB::table('registration_companions')->whereIn('registration_id', $registrationIds)->update(['price_category_id' => $categoryId]);
            DB::table('waitlist_entries')->where('event_id', $event->id)->update(['price_category_id' => $categoryId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_category_id');
        });

        Schema::table('registration_companions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_category_id');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_category_id');
        });

        Schema::dropIfExists('event_price_categories');
    }
};
