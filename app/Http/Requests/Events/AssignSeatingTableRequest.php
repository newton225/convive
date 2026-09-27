<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\Registration;
use App\Models\SeatingTable;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Placement manuel d'une inscription sur une table (README 2.6, ecran 21), etape 6 de
 * « Ordre de construction ».
 */
class AssignSeatingTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Une inscription qui n'appartient pas a cet evenement recoit 404 ici, avant meme la
     * verification de permission : meme raison que `PaymentProofController::ensureBelongsToEvent()`,
     * une URL mal assortie ne doit rien reveler de l'inscription visee.
     */
    public function authorize(): bool
    {
        $registration = $this->route('registration');

        abort_unless(
            $registration instanceof Registration && $registration->event_id === $this->event()->id,
            404,
        );

        return Gate::allows('assign', [SeatingTable::class, $this->tenant()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Nulle retire l'inscription de sa table plutot que de la deplacer. La table doit
     * appartenir a cet evenement precis : sans ce filtre, un identifiant d'une autre
     * organisation ou d'un autre evenement (meme locataire) serait accepte.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'seating_table_id' => [
                'nullable',
                'integer',
                Rule::exists('seating_tables', 'id')->where('event_id', $this->event()->id),
            ],
        ];
    }

    private function event(): Event
    {
        $event = $this->route('event');

        abort_if(! $event instanceof Event, 404);

        return $event;
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
