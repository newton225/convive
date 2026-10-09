<?php

namespace App\Http\Requests\Events;

use App\Actions\Seating\SyncSeatingTables;
use App\Models\Event;
use App\Models\EventPriceCategory;
use App\Models\PaymentAccount;
use App\Models\Registration;
use App\Models\Tenant;
use App\Settings\ReservationSettings;
use App\Support\MapLink;
use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEventRequest extends FormRequest
{
    /**
     * Le quota est obligatoire (decision du 2026-10-08) quand le formulaire envoie ses tarifs. Un
     * envoi sans tarifs (tarif unique reconstitue) n'a pas de quota a exiger.
     */
    private bool $categoriesFromForm = true;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $tenant = $this->tenant();
        $event = $this->route('event');

        return $event instanceof Event
            ? Gate::allows('update', [$event, $tenant])
            : Gate::allows('create', [Event::class, $tenant]);
    }

    /**
     * Des coordonnees collees (« 5.3364, -4.0267 ») deviennent un lien de carte avant validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('venue_map_url')) {
            $this->merge(['venue_map_url' => MapLink::normalize($this->input('venue_map_url'))]);
        }

        $this->matchPriceCategoriesByName();

        $this->categoriesFromForm = $this->has('price_categories');

        if (! $this->categoriesFromForm) {
            $event = $this->route('event');
            $existing = $event instanceof Event ? $event->priceCategories()->get() : collect();
            $price = (int) $this->input('price_per_person', 0);

            $this->merge([
                'price_categories' => $existing->isEmpty()
                    ? [['name' => 'Tarif unique', 'price' => $price, 'quota' => null]]
                    : $existing->map(fn (EventPriceCategory $category) => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'price' => $existing->count() === 1 ? $price : $category->price,
                        'quota' => $category->quota,
                    ])->all(),
            ]);
        }
    }

    /**
     * Une ligne sans identifiant dont le nom est celui d'un tarif existant est ce tarif : un
     * formulaire reenvoye tel quel (double enregistrement, page pas rechargee) ne cree pas de doublon
     * et ne retire pas le tarif existant, qui peut deja avoir ete choisi par des inscrits.
     */
    private function matchPriceCategoriesByName(): void
    {
        $event = $this->route('event');
        $rows = $this->input('price_categories');

        if (! $event instanceof Event || ! is_array($rows)) {
            return;
        }

        $existing = $event->priceCategories()->get();
        $claimed = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! empty($row['id'])) {
                continue;
            }

            $match = $existing->first(fn (EventPriceCategory $category) => ! in_array($category->id, $claimed, true)
                && mb_strtolower($category->name) === mb_strtolower(trim((string) ($row['name'] ?? ''))));

            if ($match !== null) {
                $claimed[] = $match->id;
                $rows[$index]['id'] = $match->id;
            }
        }

        $this->merge(['price_categories' => $rows]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Rien n'est obligatoire au-dela du nom : l'assistant s'enregistre a chaque etape, et un
     * evenement incomplet reste un brouillon. C'est `Event::isReadyToPublish()` qui garde la
     * publication, pas la validation du formulaire.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'subtitle' => ['nullable', 'string', 'max:200'],

            'starts_at' => ['nullable', 'date'],
            // Facultative ; apres le debut, et jamais sans lui (verifie plus bas).
            'ends_at' => ['nullable', 'date'],
            // Fenetre d'entree : vide, les portes n'ont pas d'heure d'ouverture ; la marge apres la fin
            // vaut 30 minutes si on ne la donne pas.
            'entry_opens_minutes_before' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'entry_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'venue' => ['nullable', 'string', 'max:150'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            // Presente aux invites : un service de cartes connu, jamais un site quelconque.
            'venue_map_url' => [
                'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! MapLink::isAllowed((string) $value)) {
                        $fail(__('events.errors.venue_map_url'));
                    }
                },
            ],

            // Hexadecimal, meme raison que les couleurs de marque de l'organisation
            // (CLAUDE.md, « Organisation ») : c'est ce que produit un `input[type=color]`.
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            // La salle en groupes de tables de tailles differentes (decision du 2026-09-29).
            // Sans table (decision du 2026-10-08), un simple nombre de places remplace les groupes.
            'seats_at_tables' => ['sometimes', 'boolean'],
            'free_seats' => [Rule::requiredIf(fn () => ! $this->seatsAtTables()), 'nullable', 'integer', 'min:1', 'max:100000'],
            'table_groups' => ['sometimes', 'nullable', 'array', 'max:20'],
            'table_groups.*.count' => ['required', 'integer', 'min:1', 'max:500'],
            'table_groups.*.seats' => ['required', 'integer', 'min:1', 'max:100'],
            // Toujours renseigne : 0 dit qu'un evenement est gratuit, un champ vide ne dit rien
            // (decision du proprietaire du projet, 2026-10-07).
            'price_per_person' => ['required', 'integer', 'min:0', 'max:100000000'],
            'price_categories' => ['required', 'array', 'min:1', 'max:20'],
            'price_categories.*.id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $event = $this->route('event');

                    if ($value !== null
                        && (! $event instanceof Event
                            || ! $event->priceCategories()->whereKey($value)->exists())) {
                        $fail(__('events.errors.price_category_unknown'));
                    }
                },
            ],
            'price_categories.*.name' => ['required', 'string', 'max:60'],
            'price_categories.*.price' => ['required', 'integer', 'min:0', 'max:100000000'],
            'price_categories.*.quota' => [$this->categoriesFromForm ? 'required' : 'nullable', 'integer', 'min:1'],
            'companion_limit' => ['nullable', 'integer', 'min:0', 'max:'.Event::MaximumCompanionLimit],

            'registration_deadline' => ['nullable', 'date'],
            'purge_at' => ['nullable', 'date'],
            'invitations_send_at' => ['nullable', 'date'],
            // Entre les bornes reglees depuis la console (`ReservationSettings`, decision du 2026-10-07).
            'hold_duration_minutes' => [
                'nullable', 'integer',
                'min:'.app(ReservationSettings::class)->hold_min_minutes,
                'max:'.app(ReservationSettings::class)->hold_max_minutes,
            ],

            // Aucune case cochee n'envoie aucun champ : l'absence veut dire « aucun compte ».
            'payment_accounts' => ['nullable', 'array'],
            'payment_accounts.*' => [
                'integer',
                // `payment_accounts` vit dans la base du locataire, deja active : verifier
                // l'existence dans cette table revient a verifier qu'il lui appartient.
                Rule::exists('payment_accounts', 'id'),
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectDeadlineAfterTheEvent($validator);
                $this->rejectEndBeforeStart($validator);
                $this->rejectMovingAnEventIntoThePast($validator);
                $this->rejectPaymentAccountsOfAnotherTenant($validator);
                $this->rejectChangingSeatingModeOnceGuestsAreIn($validator);
                $this->rejectTablePlanThatUnseatsGuests($validator);
                $this->validatePriceCategories($validator);
                $this->rejectQuotaAboveCapacity($validator);
            },
        ];
    }

    /**
     * @return array<int, array{id?: int|null, name: string, price: int, quota: int|null}>
     */
    public function priceCategories(): array
    {
        return $this->validated('price_categories');
    }

    /**
     * Determine whether the guests are seated at tables : yes unless the form says otherwise.
     */
    public function seatsAtTables(): bool
    {
        return $this->boolean('seats_at_tables', true);
    }

    /**
     * Get the room plan submitted, as the capacity of each table by number, or null when the
     * request says nothing about the tables (the plan is then left as it is).
     *
     * @return array<int, int>|null
     */
    public function tablePlan(): ?array
    {
        if (! $this->seatsAtTables()) {
            return [1 => (int) $this->input('free_seats')];
        }

        if ($this->has('table_groups')) {
            /** @var array<int, array{count: int|string, seats: int|string}> $groups */
            $groups = (array) $this->input('table_groups', []);

            return SyncSeatingTables::plan(array_map(fn (array $group) => [
                'count' => (int) $group['count'],
                'seats' => (int) $group['seats'],
            ], array_values($groups)));
        }

        return null;
    }

    /**
     * Le mode (avec ou sans tables) se fige des qu'une inscription occupe une place (decision du
     * 2026-10-08) : il decide ce que dit le billet et si une table est attribuee, deux choses que
     * les inscrits ont deja recues.
     */
    private function rejectChangingSeatingModeOnceGuestsAreIn(Validator $validator): void
    {
        $event = $this->route('event');

        if (! $event instanceof Event || $event->seatsAtTables() === $this->seatsAtTables()) {
            return;
        }

        if (Registration::query()->where('event_id', $event->id)->occupyingSeats()->exists()) {
            $validator->errors()->add('seats_at_tables', __('events.errors.seating_mode_locked'));
        }
    }

    /**
     * Un invite deja place ne perd jamais sa chaise, et un evenement publie ne descend pas sous
     * les places deja prises ou reservees (README 2.2).
     */
    private function rejectTablePlanThatUnseatsGuests(Validator $validator): void
    {
        $event = $this->route('event');

        if (! $event instanceof Event || $validator->errors()->hasAny(['table_groups', 'table_groups.*.count', 'table_groups.*.seats', 'free_seats', 'seats_at_tables'])) {
            return;
        }

        $plan = $this->tablePlan();

        if ($plan === null) {
            return;
        }

        foreach (app(SyncSeatingTables::class)->conflicts($event, $plan) as $message) {
            $validator->errors()->add('table_groups', $message);
        }

        $capacity = array_sum($plan);
        $taken = $event->occupiedSeats();

        if ($event->isPublished() && $capacity < $taken) {
            $validator->errors()->add('table_groups', __('seating.errors.below_taken', [
                'capacity' => $capacity,
                'taken' => $taken,
            ]));
        }
    }

    /**
     * A registration deadline later than the event itself is a data entry mistake, and it
     * would silently keep registrations open during the event.
     */
    private function rejectDeadlineAfterTheEvent(Validator $validator): void
    {
        $startsAt = $this->date('starts_at');
        $deadline = $this->date('registration_deadline');

        if ($startsAt && $deadline && $deadline->greaterThan($startsAt)) {
            $validator->errors()->add('registration_deadline', __('events.errors.deadline_after_event'));
        }
    }

    /**
     * La fin est apres le debut, et ne se donne pas sans lui.
     */
    private function rejectEndBeforeStart(Validator $validator): void
    {
        if ($validator->errors()->has('ends_at') || $validator->errors()->has('starts_at')) {
            return;
        }

        $endsAt = $this->date('ends_at');

        if ($endsAt === null) {
            return;
        }

        $startsAt = $this->date('starts_at');

        if ($startsAt === null) {
            $validator->errors()->add('ends_at', __('events.errors.ends_at_without_start'));
        } elseif ($endsAt->lessThanOrEqualTo($startsAt)) {
            $validator->errors()->add('ends_at', __('events.errors.ends_at_before_start'));
        }
    }

    /**
     * Un evenement publie ou deja reserve ne se termine pas dans le passe (decision du
     * 2026-10-09) : un invite qui a deja verse son argent recevrait un billet echu. Pour en finir,
     * on le cloture. Un evenement deja commence mais pas fini reste valable, et des dates passees
     * qu'on ne touche pas ne bloquent rien : l'evenement reste modifiable.
     */
    private function rejectMovingAnEventIntoThePast(Validator $validator): void
    {
        $event = $this->route('event');
        $startsAt = $this->date('starts_at');
        $endsAt = $this->date('ends_at');
        $end = $endsAt ?? $startsAt;

        if (! $event instanceof Event || $end === null || ! $end->isPast()) {
            return;
        }

        if ($event->published_at === null && ! $event->registrations()->exists()) {
            return;
        }

        if ($this->sameMinute($event->starts_at, $startsAt) && $this->sameMinute($event->ends_at, $endsAt)) {
            return;
        }

        $validator->errors()->add(
            $endsAt !== null ? 'ends_at' : 'starts_at',
            __($endsAt !== null ? 'events.errors.ends_at_past_when_published' : 'events.errors.starts_at_past_when_published'),
        );
    }

    private function sameMinute(?DateTimeInterface $stored, ?DateTimeInterface $submitted): bool
    {
        if ($stored === null || $submitted === null) {
            return $stored === $submitted;
        }

        return intdiv($stored->getTimestamp(), 60) === intdiv($submitted->getTimestamp(), 60);
    }

    /**
     * Filet de securite : `payment_accounts` vit deja dans la base du locataire (voir
     * `rules()`), mais un doublon dans la liste soumise passerait la regle `exists` sans etre
     * detecte autrement.
     */
    private function rejectPaymentAccountsOfAnotherTenant(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['payment_accounts', 'payment_accounts.*'])) {
            return;
        }

        $ids = array_map('intval', (array) $this->input('payment_accounts', []));

        if ($ids === []) {
            return;
        }

        $known = PaymentAccount::whereIn('id', $ids)->count();

        if ($known !== count(array_unique($ids))) {
            $validator->errors()->add('payment_accounts', __('events.errors.unknown_payment_account'));
        }
    }

    /**
     * Un quota est un plafond a l'interieur de la salle : il ne peut pas depasser la capacite
     * (decision du 2026-10-07). La capacite est celle du plan soumis, ou celle de l'evenement
     * quand le plan n'est pas touche ; sans aucune table, il n'y a rien a comparer.
     */
    private function rejectQuotaAboveCapacity(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['table_groups', 'table_groups.*.count', 'table_groups.*.seats', 'free_seats', 'seats_at_tables', 'price_categories.*.quota'])) {
            return;
        }

        $plan = $this->tablePlan();
        $event = $this->route('event');
        $capacity = $plan !== null
            ? array_sum($plan)
            : ($event instanceof Event ? $event->capacity() : 0);

        if ($capacity === 0) {
            return;
        }

        $total = 0;
        $aboveCapacity = false;

        foreach ((array) $this->input('price_categories', []) as $index => $attributes) {
            $quota = is_array($attributes) ? ($attributes['quota'] ?? null) : null;

            $total += (int) $quota;

            if ($quota !== null && $quota !== '' && (int) $quota > $capacity) {
                $aboveCapacity = true;
                $validator->errors()->add(
                    "price_categories.{$index}.quota",
                    __('events.errors.price_category_quota_above_capacity', ['capacity' => $capacity]),
                );
            }
        }

        // Les quotas se partagent la salle (decision du 2026-10-08) : leur somme ne la depasse pas.
        if ($total > $capacity && ! $aboveCapacity) {
            $validator->errors()->add('price_categories', __('events.errors.price_category_quotas_above_capacity', ['total' => $total, 'capacity' => $capacity]));
        }
    }

    private function validatePriceCategories(Validator $validator): void
    {
        if ($validator->errors()->hasAny([
            'price_categories',
            'price_categories.*.id',
            'price_categories.*.name',
            'price_categories.*.price',
            'price_categories.*.quota',
        ])) {
            return;
        }

        $categories = array_values((array) $this->input('price_categories', []));
        $names = array_map(fn (array $category) => mb_strtolower(trim($category['name'])), $categories);

        if (count($names) !== count(array_unique($names))) {
            $validator->errors()->add('price_categories', __('events.errors.price_category_duplicate'));

            return;
        }

        $event = $this->route('event');

        if (! $event instanceof Event) {
            return;
        }

        $keptIds = [];

        foreach ($categories as $index => $attributes) {
            if (empty($attributes['id'])) {
                continue;
            }

            $category = $event->priceCategories()->find((int) $attributes['id']);
            if (! $category) {
                continue;
            }

            $keptIds[] = $category->id;
            $taken = $category->seatsTaken();

            // Un tarif deja choisi garde son nom et son prix : seul son quota peut encore bouger.
            if ($category->isChosen()) {
                if (trim((string) ($attributes['name'] ?? '')) !== $category->name) {
                    $validator->errors()->add("price_categories.{$index}.name", __('events.errors.price_category_locked'));
                }

                if ((int) ($attributes['price'] ?? 0) !== $category->price) {
                    $validator->errors()->add("price_categories.{$index}.price", __('events.errors.price_category_locked'));
                }
            }

            if (($attributes['quota'] ?? null) !== null && (int) $attributes['quota'] < $taken) {
                $validator->errors()->add(
                    "price_categories.{$index}.quota",
                    __('events.errors.price_category_quota_below_taken', ['count' => $taken]),
                );
            }
        }

        foreach ($event->priceCategories()->whereNotIn('id', $keptIds)->get() as $removed) {
            if ($removed->isChosen()) {
                $validator->errors()->add('price_categories', __('events.errors.price_category_in_use'));

                return;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('organisation.errors.color'),
            'secondary_color.regex' => __('organisation.errors.color'),
        ];
    }

    /**
     * Get the attribute names used in validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('events.fields.name'),
            'subtitle' => __('events.fields.subtitle'),
            'starts_at' => __('events.fields.starts_at'),
            'ends_at' => __('events.fields.ends_at'),
            'entry_opens_minutes_before' => __('events.fields.entry_opens_minutes_before'),
            'entry_grace_minutes' => __('events.fields.entry_grace_minutes'),
            'venue' => __('events.fields.venue'),
            'free_seats' => __('events.fields.free_seats'),
            'seats_at_tables' => __('events.fields.seats_at_tables'),
            'payment_accounts' => __('events.fields.payment_accounts'),
            'venue_map_url' => __('events.fields.venue_map_url'),
            'table_groups' => __('events.fields.table_groups'),
            'table_groups.*.count' => __('events.fields.table_count'),
            'table_groups.*.seats' => __('events.fields.seats_per_table'),
            'price_per_person' => __('events.fields.price_per_person'),
            'price_categories' => __('events.fields.price_categories'),
            'price_categories.*.name' => __('events.fields.price_category_name'),
            'price_categories.*.price' => __('events.fields.price_category_price'),
            'price_categories.*.quota' => __('events.fields.price_category_quota'),
            'companion_limit' => __('events.fields.companion_limit'),
            'registration_deadline' => __('events.fields.registration_deadline'),
            'hold_duration_minutes' => __('events.fields.hold_duration_minutes'),
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
