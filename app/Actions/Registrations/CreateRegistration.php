<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Support\RegistrationReference;
use Illuminate\Support\Facades\DB;

class CreateRegistration
{
    /**
     * Create a draft registration for the given event, with its companions.
     *
     * `Draft` (README 2.1) : le formulaire est enregistre, aucune place n'est consommee
     * fermement. La reservation (`Held`) et sa verification de stock sous verrou sont tentees
     * juste apres, par l'appelant (voir `HoldRegistration`), dans la meme requete.
     *
     * @param  array{name: string, phone: string, email: string|null, unit_id: int, companions: array<int, array{name: string, unit_id: int}>}  $data
     * @return array{registration: Registration, resumeToken: string} le jeton en clair, a
     *                                                                remettre a l'invite (URL,
     *                                                                email) : seule son
     *                                                                empreinte est stockee.
     */
    public function handle(Event $event, array $data): array
    {
        return DB::transaction(function () use ($event, $data) {
            $resumeToken = Registration::generateResumeToken();

            $registration = Registration::create([
                'event_id' => $event->id,
                'reference' => RegistrationReference::next(),
                'status' => RegistrationStatus::Draft,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'unit_id' => $data['unit_id'],
                'amount_due' => $event->amountFor(count($data['companions'])),
                'party_size' => 1 + count($data['companions']),
                'resume_token_hash' => Registration::hashResumeToken($resumeToken),
            ]);

            foreach ($data['companions'] as $position => $companion) {
                $registration->companions()->create([
                    'name' => $companion['name'],
                    'unit_id' => $companion['unit_id'],
                    'position' => $position,
                ]);
            }

            return ['registration' => $registration, 'resumeToken' => $resumeToken];
        });
    }
}
