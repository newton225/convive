<?php

namespace App\Http\Requests\Events;

use App\Models\GuestClaim;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Marquer une reclamation comme traitee. L'autorisation se joue avant tout, ici : un membre sans la
 * permission ne lit aucun message d'erreur sur ce que le formulaire attendrait.
 */
class ResolveGuestClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');
        $claim = $this->route('claim');

        return $tenant instanceof Tenant
            && $claim instanceof GuestClaim
            && Gate::allows('resolve', [$claim, $tenant]);
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
