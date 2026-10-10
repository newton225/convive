<?php

/*
 * Les faits dont les campagnes QA ont besoin sur l'application de test : identifiants des unites,
 * des tarifs, des comptes de versement, de l'evenement ouvert et du complet. Appele par
 * `node qa/...` apres la preparation de l'application (voir `qa/e2e-server.mjs`).
 */

use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

echo json_encode($tenant->run(function () use ($tenant) {
    $published = Event::query()->whereNotNull('public_token')->get();

    $open = $published->first(fn (Event $event) => $event->acceptsRegistrations()
        && $event->remainingSeats() >= 30
        && $event->paymentAccounts()->get()->contains(fn (PaymentAccount $account) => $account->isPubliclyVisible()));

    $full = $published->first(fn (Event $event) => $event->capacity() > 0 && $event->remainingSeats() === 0);

    return [
        'tenantSlug' => $tenant->slug,
        'subdomain' => $tenant->subdomain,
        'eventId' => $open->id,
        'token' => $open->public_token,
        'fullToken' => $full?->public_token,
        'remainingSeats' => $open->remainingSeats(),
        'capacity' => $open->capacity(),
        'units' => Unit::active()->ordered()->get()->map(fn (Unit $unit) => ['id' => $unit->id, 'name' => $unit->name])->all(),
        'priceCategories' => $open->priceCategories()->get()->map(fn ($category) => ['id' => $category->id, 'name' => $category->name, 'price' => $category->price])->all(),
        'paymentAccounts' => $open->paymentAccounts()->get()->filter(fn (PaymentAccount $account) => $account->isPubliclyVisible())->map(fn (PaymentAccount $account) => ['id' => $account->id, 'channel' => $account->channel?->value])->values()->all(),
    ];
}), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
