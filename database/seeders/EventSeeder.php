<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\PaymentChannel;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Plusieurs evenements de l'organisation de demonstration, couvrant les statuts reellement
 * stockes (brouillon, ouvert, en cours, termine), dont un evenement complet pour eprouver la
 * liste d'attente (README 2.3). « Complet » n'est pas seme comme statut : il resulte des
 * inscriptions confirmees que `RegistrationSeeder` y ajoute ensuite (voir `Event::isFull()`).
 */
class EventSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        $tenant->run(function () {
            $accounts = $this->paymentAccounts();

            $draftStartsAt = now()->addMonths(6);
            $this->draftEvent($this->schedule(
                name: 'Convention Annuelle 2027',
                startsAt: $draftStartsAt,
                extra: ['status' => EventStatus::Draft, 'table_count' => 30, 'seats_per_table' => 10],
            ));

            $dinerStartsAt = now()->addWeeks(6);
            $this->publishedEvent($accounts, $dinerStartsAt, $this->schedule(
                name: 'Diner de Noel de l\'Association',
                startsAt: $dinerStartsAt,
                extra: ['table_count' => 15, 'seats_per_table' => 8],
            ));

            $louangeStartsAt = now()->subHour();
            $this->publishedEvent($accounts, $louangeStartsAt, $this->schedule(
                name: 'Nuit de Louange',
                startsAt: $louangeStartsAt,
                extra: ['status' => EventStatus::Ongoing, 'table_count' => 10, 'seats_per_table' => 8],
            ));

            $familleStartsAt = now()->subMonths(4);
            $this->publishedEvent($accounts, $familleStartsAt, $this->schedule(
                name: 'Soiree des Familles 2025',
                startsAt: $familleStartsAt,
                extra: ['status' => EventStatus::Closed, 'table_count' => 12, 'seats_per_table' => 8],
            ));

            // Petite capacite, deliberement : `RegistrationSeeder` la remplit entierement pour
            // que la liste d'attente ait quelque chose a montrer.
            $dejeunerStartsAt = now()->addWeeks(2);
            $this->publishedEvent($accounts, $dejeunerStartsAt, $this->schedule(
                name: 'Petit Dejeuner des Femmes Leaders',
                startsAt: $dejeunerStartsAt,
                extra: ['table_count' => 2, 'seats_per_table' => 4],
            ));
        });
    }

    /**
     * Create the draft event, unless an event of this name already exists : idempotence de
     * `DatabaseSeeder` (voir `publishedEvent()`, meme raison).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function draftEvent(array $attributes): void
    {
        if (Event::where('name', $attributes['name'])->exists()) {
            return;
        }

        Event::factory()->create($attributes);
    }

    /**
     * Create an event already handed a public link, with the given payment accounts attached,
     * unless an event of this name already exists.
     *
     * Idempotent par nom plutot que par « la table a-t-elle deja une ligne » : cette base de
     * developpement porte aussi des evenements crees a la main en testant l'interface, un garde
     * global aurait empeche ce seeder de jamais rien creer des la premiere fois qu'un membre de
     * l'equipe ouvre l'application.
     *
     * `published()` pose `published_at` a l'instant du semis : realiste pour un evenement a
     * venir, mais un evenement deja termine se serait publie bien avant sa date, jamais apres.
     * On la recalcule donc a partir de `starts_at` pour un evenement passe. Recue a part plutot
     * que relue depuis `$attributes['starts_at']` : un tableau `array<string, mixed>` perd le
     * type precis de cette entree.
     *
     * @param  array<int, int>  $paymentAccountIds
     * @param  array<string, mixed>  $attributes
     */
    private function publishedEvent(array $paymentAccountIds, CarbonImmutable $startsAt, array $attributes): void
    {
        if (Event::where('name', $attributes['name'])->exists()) {
            return;
        }

        if ($startsAt->isPast()) {
            $attributes['published_at'] = $startsAt->clone()->subWeeks(3);
        }

        $event = Event::factory()->published()->create($attributes);

        $event->paymentAccounts()->sync($paymentAccountIds);
    }

    /**
     * Make sure the tenant offers one account per mobile network, so the demo shows what a
     * guest actually chooses between. Only the missing networks are created: a replay, or an
     * account the operator added by hand, is left as it is.
     *
     * @return array<int, int>
     */
    private function paymentAccounts(): array
    {
        $networks = [
            PaymentChannel::Wave,
            PaymentChannel::OrangeMoney,
            PaymentChannel::MtnMoney,
            PaymentChannel::MoovMoney,
        ];

        foreach ($networks as $position => $channel) {
            if (! PaymentAccount::where('channel', $channel)->exists()) {
                PaymentAccount::factory()->onChannel($channel)->create(['position' => $position]);
            }
        }

        return PaymentAccount::query()->pluck('id')->all();
    }

    /**
     * Derive the deadline, purge and invitation dates from a chosen start date, the same way
     * `EventFactory::definition()` does for its own random one : passer `starts_at` dans le
     * tableau d'attributs a `create()` laisserait ces trois colonnes calculees sur la date
     * aleatoire d'origine, plus la meme depuis un evenement recent ou passe.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function schedule(string $name, CarbonImmutable $startsAt, array $extra = []): array
    {
        return array_merge([
            'name' => $name,
            'starts_at' => $startsAt,
            'registration_deadline' => $startsAt->clone()->subDays(3),
            'purge_at' => $startsAt->clone()->subDays(2),
            'invitations_send_at' => $startsAt->clone()->subDays(7),
        ], $extra);
    }
}
