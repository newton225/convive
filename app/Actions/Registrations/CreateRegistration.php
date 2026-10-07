<?php

namespace App\Actions\Registrations;

use App\Enums\RegistrationStatus;
use App\Models\Event;
use App\Models\Registration;
use App\Support\RegistrationReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRegistration
{
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
        });
    }
}
