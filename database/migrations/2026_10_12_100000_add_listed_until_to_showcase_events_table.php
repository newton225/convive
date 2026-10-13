<?php

use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moment ou l'evenement quitte la vitrine : la fin de l'evenement ou la date limite des
 * inscriptions, la premiere des deux (`Event::showcaseUntil()`). Copie de lecture centrale, comme
 * le reste de la table : la vitrine n'ouvre jamais la base d'un locataire. Les lignes deja
 * annoncees sont rattrapees ici, organisation par organisation : leur echeance vit dans la base
 * du locataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('showcase_events', function (Blueprint $table) {
            $table->dateTime('listed_until')->nullable()->index();
        });

        DB::table('showcase_events')->update(['listed_until' => DB::raw('starts_at')]);

        Tenant::query()->each(function (Tenant $tenant) {
            $tenant->run(function () use ($tenant) {
                Event::query()->whereNotNull('announced_at')->each(function (Event $event) use ($tenant) {
                    DB::connection(config('tenancy.database.central_connection'))
                        ->table('showcase_events')
                        ->where('tenant_id', $tenant->id)
                        ->where('event_id', $event->id)
                        ->update(['listed_until' => $event->showcaseUntil()]);
                });
            });
        });
    }

    public function down(): void
    {
        Schema::table('showcase_events', function (Blueprint $table) {
            $table->dropIndex(['listed_until']);
            $table->dropColumn('listed_until');
        });
    }
};
