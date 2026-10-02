<?php

/*
 * Appele par la mise en place des tests de bout en bout, apres le semis : ecrit en JSON ce dont
 * les tests ont besoin et qu'ils ne peuvent pas deviner (adresses publiques avec jeton, tarif,
 * organisation etrangere a un compte).
 */

use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$fail = function (string $message): never {
    fwrite(STDERR, $message."\n");
    exit(1);
};

$tenant = Tenant::where('name', TenantSeeder::TenantName)->first() ?? $fail("L'organisation de demonstration n'a pas ete semee.");

// Une organisation dont le compte « Lecture » de demonstration n'est pas membre : l'espace
// personnel du compte principal.
$foreign = User::where('email', 'admin@convive.com')->first()?->personalTenant()
    ?? $fail("Le compte principal n'a pas d'espace personnel.");

$state = $tenant->run(function () use ($tenant, $foreign, $fail) {
    $published = Event::query()->whereNotNull('public_token')->get();

    // Un evenement que l'invite peut reellement parcourir : ouvert, avec assez de places pour tous
    // les parcours et un compte de versement visible.
    $open = $published->first(fn (Event $event) => $event->acceptsRegistrations()
        && $event->remainingSeats() >= 30
        && $event->paymentAccounts()->get()->contains(fn (PaymentAccount $account) => $account->isPubliclyVisible()))
        ?? $fail("Aucun evenement de demonstration n'accepte d'inscription.");

    // Un evenement complet, pour la liste d'attente.
    $full = $published->first(fn (Event $event) => $event->capacity() > 0 && $event->remainingSeats() === 0)
        ?? $fail("Aucun evenement de demonstration n'est complet.");

    return [
        'tenantSlug' => $tenant->slug,
        'foreignTenantSlug' => $foreign->slug,
        'eventId' => $open->id,
        'eventName' => $open->name,
        'publicUrl' => $open->publicUrl(),
        'pricePerPerson' => $open->price_per_person,
        'fullEventUrl' => $full->publicUrl(),
        'unit' => Unit::active()->ordered()->firstOrFail()->name,
    ];
});

echo json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
