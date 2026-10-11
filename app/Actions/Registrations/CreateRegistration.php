<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Exceptions\ReservationBusy;
use App\Models\Event;
use App\Models\Registration;
use App\Support\RegistrationReference;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\DeadlockException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRegistration
{
    /**
     * Tentatives d'une ecriture refusee par la base avant d'abandonner.
     */
    public const WriteAttempts = 5;

    /**
     * Create a draft registration for the given event, with its companions.
     *
     * `Draft` (README 2.1) : le formulaire est enregistre, aucune place n'est consommee
     * fermement. La reservation (`Held`) et sa verification de stock sous verrou sont tentees
     * juste apres, par l'appelant (voir `HoldRegistration`), dans la meme requete.
     *
     * @param  array{name: string, phone: string, email: string|null, unit_id: int, price_category_id?: int|null, companions: array<int, array{name: string, unit_id: int, price_category_id?: int|null}>}  $data
     * @return array{registration: Registration, resumeToken: string} le jeton en clair, a
     *                                                                remettre a l'invite (URL,
     *                                                                email) : seule son
     *                                                                empreinte est stockee.
     */
    public function handle(Event $event, array $data): array
    {
        // Ces ecritures se disputaient la base avec celles de toutes les autres inscriptions : SQLite
        // refuse alors l'ecriture au lieu de l'attendre, et retenter aussitot relance la collision
        // (campagne `qa/race.mjs` du 2026-10-11). Un verrou court par evenement les met en file, a
        // part de celui des places (`HoldRegistration`) pour qu'aucun des deux n'attende l'autre.
        // Si la base refuse encore, l'invite lit un message qui dit de reessayer (`ReservationBusy`).
        try {
            return Cache::lock("event:{$event->id}:registering", 15)
                ->block(10, fn () => $this->create($event, $data));
        } catch (DeadlockException|LockTimeoutException $exception) {
            throw new ReservationBusy(previous: $exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{registration: Registration, resumeToken: string}
     */
    private function create(Event $event, array $data): array
    {
        return DB::transaction(function () use ($event, $data) {
            $resumeToken = Registration::generateResumeToken();
            $categories = $event->priceCategories()->get()->keyBy('id');
            $defaultCategoryId = $categories->count() === 1 ? $categories->keys()->first() : null;
            $guestCategoryId = $data['price_category_id'] ?? $defaultCategoryId;
            $companions = array_map(fn (array $companion) => [
                ...$companion,
                'price_category_id' => $companion['price_category_id'] ?? $defaultCategoryId,
            ], $data['companions']);
            $categoryIds = [
                $guestCategoryId,
                ...array_column($companions, 'price_category_id'),
            ];
            $selectedCategoryIds = array_values(array_filter($categoryIds));
            $knownCategoryIds = array_values(array_unique($selectedCategoryIds));

            if ($categories->isNotEmpty()
                && (count($selectedCategoryIds) !== 1 + count($companions)
                    || $categories->only($knownCategoryIds)->count() !== count($knownCategoryIds))) {
                throw ValidationException::withMessages([
                    'price_category_id' => __('guest.registration.errors.price_category_unknown'),
                ]);
            }

            $amountDue = $categories->isEmpty()
                ? $event->amountFor(count($companions))
                : array_sum(array_map(
                    fn (int|string|null $categoryId) => (int) $categories->get($categoryId)?->price,
                    $categoryIds,
                ));

            $registration = Registration::create([
                'event_id' => $event->id,
                'reference' => RegistrationReference::next(),
                'status' => RegistrationStatus::Draft,
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'unit_id' => $data['unit_id'],
                'price_category_id' => $guestCategoryId,
                'amount_due' => $amountDue,
                'party_size' => 1 + count($companions),
                'resume_token_hash' => Registration::hashResumeToken($resumeToken),
            ]);

            foreach ($companions as $position => $companion) {
                $registration->companions()->create([
                    'name' => $companion['name'],
                    'unit_id' => $companion['unit_id'],
                    'price_category_id' => $companion['price_category_id'],
                    'position' => $position,
                ]);
            }

            return ['registration' => $registration, 'resumeToken' => $resumeToken];
        }, self::WriteAttempts);
    }
}
