<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\UpdateExportLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\UpdateExportLimitRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Le reglage de la limite d'exports, depuis l'ecran Securite de la console.
 */
class ExportLimitController extends Controller
{
    public function __invoke(UpdateExportLimitRequest $request, UpdateExportLimit $update): RedirectResponse
    {
        $update->handle($request->integer('exports_per_hour'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('console.security.export_limit.flash')]);

        return to_route('console.security');
    }
}
