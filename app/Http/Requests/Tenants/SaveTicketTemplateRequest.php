<?php

namespace App\Http\Requests\Tenants;

use App\Enums\TicketModel;
use App\Models\Tenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Le gabarit du billet (README ecran 15) : memes permissions que la marque, puisque le billet
 * en porte les couleurs, le logo, le cachet et la signature.
 */
class SaveTicketTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateBrand', $this->tenant());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ticket_model' => ['required', Rule::enum(TicketModel::class)],
            'ticket_element_logo' => ['boolean'],
            'ticket_element_stamp' => ['boolean'],
            'ticket_element_signature' => ['boolean'],
            'ticket_element_companions' => ['boolean'],
        ];
    }

    private function tenant(): Tenant
    {
        $tenant = $this->route('tenant');

        abort_if(! $tenant instanceof Tenant, 404);

        return $tenant;
    }
}
