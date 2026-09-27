<?php

namespace Database\Seeders;

use App\Actions\Seating\AssignTable;
use App\Models\Event;
use App\Models\Registration;
use App\Models\RegistrationCompanion;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use Closure;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Inscriptions reparties sur tous les statuts, avec accompagnateurs et unites, tables
 * attribuees pour les inscriptions confirmees (README 2.5, 2.6). S'appuie sur les evenements de
 * `EventSeeder` : rien n'est seme pour un evenement qui n'y a pas ete cree.
 */
class RegistrationSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('name', TenantSeeder::TenantName)->first();

        if (! $tenant) {
            return;
        }

        $tenant->run(function () {
            $this->seedOpenEvent();
            $this->seedOngoingEvent();
            $this->seedClosedEvent();
            $this->seedFullEvent();
        });
    }

    /**
     * L'evenement ouvert (README ecran 9) : la vue la plus consultee, elle doit montrer les
     * sept statuts reellement stockes de `RegistrationStatus`. Les trois `proofSubmitted()`
     * sont repris par `PaymentProofSeeder` pour leurs preuves saines.
     */
    private function seedOpenEvent(): void
    {
        $event = $this->freshEvent("Diner de Noel de l'Association");

        if (! $event) {
            return;
        }

        $this->registration($event, fn ($f) => $f);
        $this->registration($event, fn ($f) => $f);
        $this->registration($event, fn ($f) => $f->held());
        $this->registration($event, fn ($f) => $f->held(), companions: 1);
        $this->registration($event, fn ($f) => $f->proofSubmitted());
        $this->registration($event, fn ($f) => $f->proofSubmitted(), companions: 1);
        $this->registration($event, fn ($f) => $f->proofSubmitted());
        $this->seat($this->registration($event, fn ($f) => $f->confirmed(), companions: 2));
        $this->seat($this->registration($event, fn ($f) => $f->confirmed()));
        $this->registration($event, fn ($f) => $f->expired());
        $this->registration($event, fn ($f) => $f->proofRejected());
        $this->registration($event, fn ($f) => $f->cancelled());
    }

    /**
     * L'evenement en cours : deja en salle, donc presque uniquement des inscriptions confirmees
     * et placees.
     */
    private function seedOngoingEvent(): void
    {
        $event = $this->freshEvent('Nuit de Louange');

        if (! $event) {
            return;
        }

        for ($i = 0; $i < 9; $i++) {
            $this->seat($this->registration($event, fn ($f) => $f->confirmed(), companions: $i % 3 === 0 ? 1 : 0));
        }

        $this->registration($event, fn ($f) => $f->expired());
    }

    /**
     * L'evenement termine : une trace historique, tables deja attribuees.
     */
    private function seedClosedEvent(): void
    {
        $event = $this->freshEvent('Soiree des Familles 2025');

        if (! $event) {
            return;
        }

        for ($i = 0; $i < 10; $i++) {
            $this->seat($this->registration($event, fn ($f) => $f->confirmed(), companions: $i % 4 === 0 ? 2 : 0));
        }

        $this->registration($event, fn ($f) => $f->cancelled());
        $this->registration($event, fn ($f) => $f->expired());
    }

    /**
     * L'evenement complet (README 2.3) : quatre inscriptions de deux personnes remplissent
     * exactement les huit places de l'evenement, plus une liste d'attente pour l'eprouver.
     */
    private function seedFullEvent(): void
    {
        $event = $this->freshEvent('Petit Dejeuner des Femmes Leaders');

        if (! $event) {
            return;
        }

        for ($i = 0; $i < 4; $i++) {
            $this->seat($this->registration($event, fn ($f) => $f->confirmed(), companions: 1));
        }

        WaitlistEntry::factory()->create(['event_id' => $event->id]);
        WaitlistEntry::factory()->invited()->create(['event_id' => $event->id]);
    }

    /**
     * Get the named event, unless it is missing or already carries registrations.
     *
     * Par evenement plutot qu'un garde global sur la table entiere : cette base de
     * developpement porte aussi des inscriptions prises a la main en testant l'interface, un
     * garde global aurait empeche ce seeder de jamais rien creer.
     */
    private function freshEvent(string $name): ?Event
    {
        $event = Event::where('name', $name)->first();

        if (! $event || $event->registrations()->exists()) {
            return null;
        }

        return $event;
    }

    /**
     * @param  Closure(RegistrationFactory): RegistrationFactory  $state
     */
    private function registration(Event $event, Closure $state, int $companions = 0): Registration
    {
        // Base sans accompagnateur : le montant du reste celui de la factory (0) sans cette
        // ligne, alors qu'une preuve deposee pour zero franc n'a pas de sens. `configure()`
        // (voir `RegistrationFactory`) le recalcule par-dessus des que des accompagnateurs sont
        // rattaches.
        $factory = $state(Registration::factory())->state([
            'event_id' => $event->id,
            'amount_due' => $event->amountFor(0),
        ]);

        if ($companions > 0) {
            // Nom de relation explicite : `has()` devine sinon `registrationCompanions`, la
            // convention de pluriel du nom de classe, alors que la relation s'appelle
            // `companions()` sur `Registration`.
            $factory = $factory->has(RegistrationCompanion::factory()->count($companions), 'companions');
        }

        $registration = $factory->create();

        // `Factory::create()` peut renvoyer une collection quand `count()` a ete appele : jamais
        // le cas ici, mais son type de retour reste `TModel|Collection` pour PHPStan.
        if ($registration instanceof Collection) {
            throw new LogicException('La fabrique a cree plusieurs inscriptions au lieu d\'une seule.');
        }

        return $registration;
    }

    /**
     * Attribuer une table a l'inscription, comme le ferait la validation de sa preuve
     * (`App\Actions\PaymentProofs\ValidatePaymentProof`) : reutilise la meme Action plutot que
     * de poser une ligne `RegistrationTableAssignment` a la main.
     */
    private function seat(Registration $registration): void
    {
        app(AssignTable::class)->handle($registration);
    }
}
