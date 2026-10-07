<?php

namespace App\Actions\Waitlist;

use App\Enums\WaitlistStatus;
use App\Models\Event;
use App\Models\WaitlistEntry;

/**
 * Inscrit un invite en liste d'attente (README 2.3), quand l'evenement est complet.
 */
class JoinWaitlist
{
    /**
     * @param  array{name: string, phone: string, unit_id: int, price_category_id: int|null, companions: array<int, array{name: string, unit_id: int, price_category_id?: int|null}>}  $data
     * @return array{entry: WaitlistEntry, resumeToken: string}
     */
    public function handle(Event $event, array $data): array
    {
        $resumeToken = WaitlistEntry::generateResumeToken();

        $entry = WaitlistEntry::create([
            'event_id' => $event->id,
            'status' => WaitlistStatus::Waiting,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'unit_id' => $data['unit_id'],
            'price_category_id' => $data['price_category_id'],
            'party_size' => 1 + count($data['companions']),
            'companions' => $data['companions'],
            'resume_token_hash' => WaitlistEntry::hashResumeToken($resumeToken),
        ]);

        return ['entry' => $entry, 'resumeToken' => $resumeToken];
    }
}
