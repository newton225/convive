<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\SeatingTable;
use App\Models\Tenant;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

/**
 * La capacite d'une table precise, depuis le plan de salle (README ecran 21, decision du
 * 2026-09-29). Jamais sous le nombre de personnes deja assises a cette table, jamais une capacite
 * totale sous les places deja prises d'un evenement publie.
 */
class UpdateSeatingTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::allows('resize', [SeatingTable::class, $tenant]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $table = $this->route('table');
                $event = $this->route('event');

                if ($validator->errors()->has('capacity') || ! $table instanceof SeatingTable || ! $event instanceof Event) {
                    return;
                }

                $capacity = $this->integer('capacity');
                $table->load('assignments.registration');
                $seated = $table->capacity - $table->remainingCapacity();

                if ($capacity < $seated) {
                    $validator->errors()->add('capacity', trans_choice('seating.errors.table_too_small', $seated, [
                        'number' => $table->number,
                    ]));

                    return;
                }

                $total = $event->capacity() - $table->capacity + $capacity;
                $taken = $event->occupiedSeats();

                if ($event->isPublished() && $total < $taken) {
                    $validator->errors()->add('capacity', __('seating.errors.below_taken', [
                        'capacity' => $total,
                        'taken' => $taken,
                    ]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['capacity' => __('seating.capacity.label')];
    }
}
