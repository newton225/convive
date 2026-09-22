<?php

namespace Database\Factories;

use App\Enums\PlanCode;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['code' => PlanCode::Essential->value, ...PlanCode::Essential->definition()];
    }

    /**
     * Indicate that the plan is the given one, with its default definition.
     */
    public function of(PlanCode $code): static
    {
        return $this->state(fn () => ['code' => $code->value, ...$code->definition()]);
    }
}
