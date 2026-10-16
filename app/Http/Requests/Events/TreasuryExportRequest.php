<?php

namespace App\Http\Requests\Events;

use App\Models\Tenant;
use App\Support\EventReport;
use Illuminate\Support\Facades\Gate;

/**
 * L'export PDF de la tresorerie : voir l'ecran ne suffit pas, il faut la permission d'export des
 * rapports (`reports.export`), comme pour le rapport d'un evenement. Un export legitime reste une fuite
 * possible (SECURITY.md M3) : il est limite en debit et journalise.
 */
class TreasuryExportRequest extends TreasuryRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant && Gate::allows('export', [EventReport::class, $tenant]);
    }
}
