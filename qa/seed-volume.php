<?php

/*
 * Donne du volume a l'application de test pour les mesures de performance : un evenement de 3 000
 * places, publie, avec des milliers d'inscriptions de tous les statuts (reservees, preuves a
 * verifier, confirmees, expirees), des accompagnateurs et des preuves.
 *
 * Usage : php qa/seed-volume.php <nombre d'inscriptions>
 * Sortie : JSON { eventId, token, registrations, proofs, companions }.
 */

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\PaymentProof;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Models\Unit;
use Database\Seeders\TenantSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$count = (int) ($argv[1] ?? 3000);

$tenant = Tenant::where('name', TenantSeeder::TenantName)->firstOrFail();

echo json_encode($tenant->run(function () use ($count) {
    $event = Event::factory()->published()->create([
        'name' => 'Evenement de volume',
        'tables' => [300, 10],
        'starts_at' => now()->addMonths(2),
    ]);
    $event->paymentAccounts()->sync(PaymentAccount::publiclyVisible()->pluck('id')->all());
    $category = EventPriceCategory::factory()->create(['event_id' => $event->id, 'name' => 'Standard', 'price' => 10000, 'quota' => null]);
    $units = Unit::active()->pluck('id')->all();

    $statuses = [
        RegistrationStatus::Confirmed->value => 0.45,
        RegistrationStatus::ProofSubmitted->value => 0.2,
        RegistrationStatus::Held->value => 0.1,
        RegistrationStatus::Expired->value => 0.15,
        RegistrationStatus::ProofRejected->value => 0.05,
        RegistrationStatus::Cancelled->value => 0.05,
    ];

    $proofs = 0;
    $companions = 0;

    DB::connection()->disableQueryLog();

    for ($index = 0; $index < $count; $index++) {
        $roll = ($index % 100) / 100;
        $cumulative = 0.0;
        $status = RegistrationStatus::Confirmed->value;

        foreach ($statuses as $candidate => $share) {
            $cumulative += $share;

            if ($roll < $cumulative) {
                $status = $candidate;
                break;
            }
        }

        $withCompanions = $index % 5 === 0;
        $registration = Registration::factory()->create([
            'event_id' => $event->id,
            'status' => $status,
            'unit_id' => $units[$index % count($units)],
            'price_category_id' => $category->id,
            'party_size' => $withCompanions ? 3 : 1,
            'amount_due' => $withCompanions ? 30000 : 10000,
            'held_until' => in_array($status, ['held', 'proof_submitted'], true) ? now()->addDay() : null,
        ]);

        if ($withCompanions) {
            RegistrationCompanion::factory()->count(2)->create([
                'registration_id' => $registration->id,
                'unit_id' => $units[$index % count($units)],
                'price_category_id' => $category->id,
            ]);
            $companions += 2;
        }

        if (in_array($status, ['proof_submitted', 'confirmed', 'proof_rejected'], true)) {
            PaymentProof::factory()->create(['registration_id' => $registration->id]);
            $proofs++;
        }
    }

    return [
        'eventId' => $event->id,
        'token' => $event->public_token,
        'registrations' => $count,
        'proofs' => $proofs,
        'companions' => $companions,
    ];
}), JSON_UNESCAPED_SLASHES);
