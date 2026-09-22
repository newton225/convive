<?php

namespace App\Actions\Waitlist;

use App\Actions\Registrations\CreateRegistration;
use App\Actions\Registrations\HoldRegistration;
use App\Enums\WaitlistStatus;
use App\Models\Registration;
use App\Models\WaitlistEntry;

/**
 * Transforme une entree de liste d'attente invitee en une vraie inscription reservee
 * (README 2.3) : les donnees collectees a l'inscription sur la liste servent telles quelles,
 * l'invite n'a rien a ressaisir.
 */
class FinalizeWaitlistEntry
{
    /**
     * @return array{registration: Registration, resumeToken: string}|null null si la place a
     *                                                                     disparu entre-temps.
     */
    public function handle(WaitlistEntry $entry): ?array
    {
        $event = $entry->event;

        $created = app(CreateRegistration::class)->handle($event, [
            'name' => $entry->name,
            'phone' => $entry->phone,
            'unit_id' => $entry->unit_id,
            'companions' => $entry->companions,
        ]);

        $registration = $created['registration'];

        if (! app(HoldRegistration::class)->handle($event, $registration)) {
            $registration->delete();

            return null;
        }

        $entry->update(['status' => WaitlistStatus::Converted]);

        return $created;
    }
}
