<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\PaymentAccount;
use App\Models\Tenant;
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

            // Hexadecimal, meme raison que les couleurs de marque de l'organisation
            // (CLAUDE.md, « Organisation ») : c'est ce que produit un `input[type=color]`.
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],

            'table_count' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'seats_per_table' => ['nullable', 'integer', 'min:0', 'max:100'],
            'price_per_person' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'companion_limit' => ['nullable', 'integer', 'min:0', 'max:'.Event::MaximumCompanionLimit],

            'registration_deadline' => ['nullable', 'date'],
            'purge_at' => ['nullable', 'date'],
            'invitations_send_at' => ['nullable', 'date'],
            'hold_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],

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
            },
        ];
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
            'table_count' => __('events.fields.table_count'),
            'seats_per_table' => __('events.fields.seats_per_table'),
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
