<?php

/*
 * Prepare le test de concurrence : ne laisse qu'un nombre donne de places libres sur l'evenement
 * ouvert de l'application de test, en occupant le reste par des inscriptions confirmees.
 *
 * Usage : php qa/race-prepare.php <places libres a laisser>
 * Sortie : JSON { eventId, unitId, categoryId, capacity, remaining }.
 */

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$free = (int) ($argv[1] ?? 1);

$tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

echo json_encode($tenant->run(function () use ($free) {
    $event = Event::query()->whereNotNull('public_token')->get()
        ->first(fn (Event $candidate) => $candidate->acceptsRegistrations() && $candidate->remainingSeats() >= 30);

    // Aucun quota par tarif : seule la capacite de la salle limite, c'est elle qu'on eprouve.
    $category = $event->priceCategories()->get()->first(fn ($candidate) => $candidate->quota === null)
        ?? $event->priceCategories()->firstOrFail();
    $category->update(['quota' => null]);

    $toFill = $event->remainingSeats() - $free;

    if ($toFill > 0) {
        Registration::factory()->confirmed()->create([
            'event_id' => $event->id,
            'party_size' => $toFill,
            'unit_id' => Unit::active()->firstOrFail()->id,
            'price_category_id' => $category->id,
            'status' => RegistrationStatus::Confirmed,
        ]);
    }

    return [
        'eventId' => $event->id,
        'unitId' => Unit::active()->firstOrFail()->id,
        'categoryId' => $category->id,
        'capacity' => $event->capacity(),
        'remaining' => $event->fresh()->remainingSeats(),
    ];
}), JSON_UNESCAPED_SLASHES);
