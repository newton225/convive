<?php

namespace App\Http\Requests\Events;

use App\Actions\Seating\SyncSeatingTables;
use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
use App\Settings\ReservationSettings;
use App\Support\MapLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveEventRequest extends FormRequest
{
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
            'table_groups' => ['sometimes', 'nullable', 'array', 'max:20'],
            'table_groups.*.count' => ['required', 'integer', 'min:1', 'max:500'],
            'table_groups.*.seats' => ['required', 'integer', 'min:1', 'max:100'],
            // Toujours renseigne : 0 dit qu'un evenement est gratuit, un champ vide ne dit rien
            // (decision du proprietaire du projet, 2026-10-07).
            'price_per_person' => ['required', 'integer', 'min:0', 'max:100000000'],
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

            'payment_accounts' => ['present', 'array'],
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
                $this->rejectPaymentAccountsOfAnotherTenant($validator);
                $this->rejectTablePlanThatUnseatsGuests($validator);
            },
        ];
    }

    /**
     * Get the room plan submitted, as the capacity of each table by number, or null when the
     * request says nothing about the tables (the plan is then left as it is).
     *
     * @return array<int, int>|null
     */
    public function tablePlan(): ?array
    {
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
     * Un invite deja place ne perd jamais sa chaise, et un evenement publie ne descend pas sous
     * les places deja prises ou reservees (README 2.2).
     */
    private function rejectTablePlanThatUnseatsGuests(Validator $validator): void
    {
        $event = $this->route('event');

        if (! $event instanceof Event || $validator->errors()->hasAny(['table_groups', 'table_groups.*.count', 'table_groups.*.seats'])) {
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
            'venue' => __('events.fields.venue'),
            'venue_map_url' => __('events.fields.venue_map_url'),
            'table_groups' => __('events.fields.table_groups'),
            'table_groups.*.count' => __('events.fields.table_count'),
            'table_groups.*.seats' => __('events.fields.seats_per_table'),
            'price_per_person' => __('events.fields.price_per_person'),
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
