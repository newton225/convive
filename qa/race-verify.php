<?php

/*
 * Releve, apres le test de concurrence, ce que la base dit vraiment : places occupees, capacite,
 * doublons de sequence de reservation.
 *
 * Usage : php qa/race-verify.php <identifiant d'evenement>
 */

use App\Models\Event;
use App\Models\Registration;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

echo json_encode($tenant->run(function () use ($argv) {
    $event = Event::findOrFail((int) $argv[1]);

    $sequences = Registration::where('event_id', $event->id)->whereNotNull('hold_sequence')->pluck('hold_sequence');

    return [
        'capacity' => $event->capacity(),
        'occupied' => $event->occupiedSeats(),
        'remaining' => $event->remainingSeats(),
        'holdSequences' => $sequences->count(),
        'distinctHoldSequences' => $sequences->unique()->count(),
        'heldByRace' => Registration::where('event_id', $event->id)->where('name', 'like', 'Course %')->whereIn('status', ['held', 'confirmed'])->count(),
    ];
}), JSON_UNESCAPED_SLASHES);
