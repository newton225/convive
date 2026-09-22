<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'number' => 'F-'.fake()->unique()->numerify('######'),
            'amount' => 25000,
            'currency' => 'XOF',
            'status' => InvoiceStatus::Paid,
            'issued_at' => now(),
            'paid_at' => now(),
        ];
    }
}
