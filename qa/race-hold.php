<?php

/*
 * Une tentative de reservation, dans son propre processus PHP : le test de concurrence (`qa/race.mjs`)
 * en lance des dizaines en meme temps sur la meme base. Chaque processus est un vrai serveur
 * d'application concurrent : memes verrous (`Cache::lock`), meme contrainte d'unicite, meme base.
 *
 * Usage : php qa/race-hold.php <numero> <identifiant d'evenement> <identifiant d'unite> <identifiant de tarif>
 * Sortie : une ligne JSON { "held": bool, "error": string|null, "ms": float }.
 */

use App\Actions\Registrations\CreateRegistration;
use App\Actions\Registrations\HoldRegistration;
use App\Models\Event;
use App\Models\Tenant;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[$script, $number, $eventId, $unitId, $categoryId] = array_pad($argv, 5, null);

$started = microtime(true);
$held = false;
$error = null;

try {
    $tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

    $held = $tenant->run(function () use ($number, $eventId, $unitId, $categoryId) {
        $event = Event::findOrFail((int) $eventId);

        $created = app(CreateRegistration::class)->handle($event, [
            'name' => "Course {$number}",
            'phone' => '+22570'.str_pad((string) (700000 + (int) $number), 6, '0', STR_PAD_LEFT).'0',
            'email' => null,
            'unit_id' => (int) $unitId,
            'price_category_id' => (int) $categoryId,
            'companions' => [],
        ]);

        return app(HoldRegistration::class)->handle($event, $created['registration']);
    });
} catch (Throwable $exception) {
    $error = get_class($exception).': '.mb_substr($exception->getMessage(), 0, 160);
}

echo json_encode([
    'number' => (int) $number,
    'held' => $held,
    'error' => $error,
    'ms' => round((microtime(true) - $started) * 1000, 1),
]);
