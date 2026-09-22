<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Unit;
use App\Models\UnitSeparationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitSeparationRule>
 */
class UnitSeparationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Reutilise deux unites existantes plutot que d'en fabriquer : comme dans
     * `RegistrationFactory`, le nom d'une unite est unique dans la base du locataire, et celles
     * de depart occupent deja tout le catalogue par defaut (voir CLAUDE.md, « Unites »).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ids = Unit::query()->inRandomOrder()->limit(2)->pluck('id');

        [$unitAId, $unitBId] = UnitSeparationRule::canonicalPair(
            $ids->get(0) ?? Unit::factory()->create()->id,
            $ids->get(1) ?? Unit::factory()->create()->id,
        );

        return [
            'event_id' => Event::factory(),
            'unit_a_id' => $unitAId,
            'unit_b_id' => $unitBId,
        ];
    }

    /**
     * Indicate the pair of units this rule separates.
     */
    public function between(int $unitId, int $otherUnitId): static
    {
        [$a, $b] = UnitSeparationRule::canonicalPair($unitId, $otherUnitId);

        return $this->state(fn () => ['unit_a_id' => $a, 'unit_b_id' => $b]);
    }
}
