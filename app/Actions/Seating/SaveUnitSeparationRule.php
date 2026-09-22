<?php

namespace App\Actions\Seating;

use App\Models\Event;
use App\Models\UnitSeparationRule;

/**
 * Gere les regles de separation entre unites d'un evenement (README ecran 21, README 2.6).
 */
class SaveUnitSeparationRule
{
    /**
     * Create the rule, or return the existing one for the same pair.
     *
     * La paire est canonisee avant l'ecriture (`UnitSeparationRule::canonicalPair()`) : la
     * contrainte d'unicite en base porte sur l'ordre canonique, jamais sur l'ordre choisi a
     * l'ecran.
     */
    public function store(Event $event, int $unitId, int $otherUnitId): UnitSeparationRule
    {
        [$unitAId, $unitBId] = UnitSeparationRule::canonicalPair($unitId, $otherUnitId);

        $rule = UnitSeparationRule::firstOrCreate([
            'event_id' => $event->id,
            'unit_a_id' => $unitAId,
            'unit_b_id' => $unitBId,
        ]);

        if ($rule->wasRecentlyCreated) {
            activity()
                ->performedOn($rule)
                ->event('created')
                ->withProperties([
                    'attributes' => ['event_id' => $event->id, 'unit_a_id' => $unitAId, 'unit_b_id' => $unitBId],
                ])
                ->log('seating.constraint_created');
        }

        return $rule;
    }

    /**
     * Remove the rule.
     */
    public function delete(UnitSeparationRule $rule): void
    {
        activity()
            ->event('deleted')
            ->withProperties([
                'old' => ['event_id' => $rule->event_id, 'unit_a_id' => $rule->unit_a_id, 'unit_b_id' => $rule->unit_b_id],
            ])
            ->log('seating.constraint_deleted');

        $rule->delete();
    }
}
