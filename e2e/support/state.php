<?php

/*
 * Appele par la mise en place des tests de bout en bout, apres le semis : ecrit en JSON ce dont
 * les tests ont besoin et qu'ils ne peuvent pas deviner, l'adresse publique (avec jeton) d'un
 * evenement de demonstration ouvert aux inscriptions.
 */

use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

$state = $tenant->run(function () use ($tenant) {
    // Un evenement que l'invite peut reellement parcourir : publie, ouvert, avec des places et un
    // compte de versement visible.
    $event = Event::query()
        ->whereNotNull('public_token')
        ->get()
        ->first(fn (Event $event) => $event->acceptsRegistrations()
            && $event->remainingSeats() >= 3
            && $event->paymentAccounts()->get()->contains(fn (PaymentAccount $account) => $account->isPubliclyVisible()));

    if ($event === null) {
        fwrite(STDERR, "Aucun evenement de demonstration n'accepte d'inscription.\n");
        exit(1);
    }

    return [
        'tenantSlug' => $tenant->slug,
        'eventId' => $event->id,
        'eventName' => $event->name,
        'publicUrl' => $event->publicUrl(),
        'unit' => Unit::active()->ordered()->firstOrFail()->name,
    ];
});

echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
