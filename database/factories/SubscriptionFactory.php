<?php

namespace Database\Factories;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plan_id' => fn () => Plan::ensure(PlanCode::Association)->id,
            'status' => SubscriptionStatus::Active,
        ];
    }

    /**
     * Indicate that the subscription runs on the given plan.
     */
    public function onPlan(PlanCode $code): static
    {
        return $this->state(fn () => ['plan_id' => Plan::ensure($code)->id]);
    }

    /**
     * Indicate that the last payment failed the given number of days ago.
     */
    public function pastDue(int $daysAgo = 0): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::PastDue,
            'past_due_since' => now()->subDays($daysAgo),
        ]);
    }

    /**
     * Indicate that the subscription is suspended for non-payment.
     */
    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => SubscriptionStatus::Suspended,
            'past_due_since' => now()->subDays(10),
            'suspended_at' => now(),
        ]);
    }
}
