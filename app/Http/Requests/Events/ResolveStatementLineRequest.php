<?php

namespace App\Http\Requests\Events;

use App\Models\Event;
use App\Models\StatementLine;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Resolution manuelle d'une ligne de releve (etape 9, ecran 19).
 */
class ResolveStatementLineRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Meme garde 404 que `CancelRegistrationRequest` : une ligne d'un autre evenement ne doit
     * rien reveler d'elle-meme avant meme la verification de permission.
     */
    public function authorize(): bool
    {
        $line = $this->route('line');

        abort_unless(
            $line instanceof StatementLine && $line->statementImport->event_id === $this->event()->id,
            404,
        );

        return Gate::allows('resolve', [$line, $this->tenant()]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * `registration_id` absent ou nul : la ligne est marquee vue, sans inscription.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_id' => [
                'nullable',
                'integer',
                Rule::exists('registrations', 'id')->where('event_id', $this->event()->id),
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
