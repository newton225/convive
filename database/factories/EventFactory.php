<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\SeatingTable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * La salle que la fabrique pose a la creation : vingt tables de dix places par defaut, `null`
     * pour un evenement sans aucune table. Portee d'une instance a la suivante (`newInstance()`),
     * comme les etats.
     *
     * @var array{0: int, 1: int}|null
     */
    private ?array $room = [20, 10];

    /**
     * Create the event with its room. La capacite d'un evenement est la somme des places de ses
     * tables (`Event::capacity()`) : un evenement sans table n'aurait aucune place.
     *
     * La cle `tables` decrit la salle sans etre une colonne : `[nombre de tables, places par
     * table]`, ou `null` pour un evenement sans aucune table, quand le test pose les siennes.
     *
     * Laravel rappelle `create()` sur une nouvelle instance des qu'on lui passe des attributs : les
     * tables ne sont posees qu'a l'appel final, sans attributs, pour ne l'etre qu'une fois.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        if (is_callable($attributes)) {
            return parent::create($attributes, $parent);
        }

        if (array_key_exists('tables', $attributes)) {
            $factory = $this->state(Arr::except($attributes, ['tables']));
            $factory->room = $attributes['tables'];

            return $factory->create([], $parent);
        }

        if ($attributes !== []) {
            return parent::create($attributes, $parent);
        }

        $created = parent::create([], $parent);
        $room = $this->room;

        if ($room !== null && $room[0] > 0 && $room[1] > 0) {
            Collection::wrap($created)->each(fn (Event $event) => self::furnish($event, $room[0], $room[1]));
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = [])
    {
        $instance = parent::newInstance($arguments);
        $instance->room = $this->room;

        return $instance;
    }

    private static function furnish(Event $event, int $tables, int $seats): void
    {
        SeatingTable::insert(array_map(fn (int $number) => [
            'event_id' => $event->id,
            'number' => $number,
            'capacity' => $seats,
            'created_at' => now(),
            'updated_at' => now(),
        ], range(1, $tables)));
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+2 weeks', '+3 months');

        return [
            'name' => 'Diner '.fake()->unique()->word().' '.fake()->word(),
            'subtitle' => fake()->sentence(4),
            'status' => EventStatus::Draft,
            'starts_at' => $startsAt,
            'venue' => 'Hotel Ivoire',
            'venue_address' => 'Boulevard Hassan II, Cocody, Abidjan',
            'price_per_person' => 15000,
            'companion_limit' => Event::MaximumCompanionLimit,
            'registration_deadline' => (clone $startsAt)->modify('-3 days'),
            'purge_at' => (clone $startsAt)->modify('-2 days'),
            'invitations_send_at' => (clone $startsAt)->modify('-7 days'),
            'hold_duration_minutes' => Event::DefaultHoldDurationMinutes,
        ];
    }

    /**
     * Indicate that the event is open to registrations.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Open]);
    }

    /**
     * Indicate that a public link has been handed out.
     */
    public function published(): static
    {
        return $this->open()->state(fn (array $attributes) => [
            'public_token' => bin2hex(random_bytes(Event::PublicTokenBytes)),
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the event is over.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => EventStatus::Closed]);
    }
}
