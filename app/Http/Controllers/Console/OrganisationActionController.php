<?php

namespace App\Http\Controllers\Console;

use App\Actions\Console\ManageOrganisation;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Console\ChangeOrganisationPlanRequest;
use App\Http\Requests\Console\ExtendOrganisationTrialRequest;
use App\Http\Requests\Console\ScheduleOrganisationDeletionRequest;
use App\Http\Requests\Console\SuspendOrganisationRequest;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

/**
 * Les actions de l'editeur sur une organisation (README section 3 et ecran 28). La zone
 * `organisation_actions` des routes les reserve aux Fondateurs et a la Comptabilite ; les regles
 * sont dans `ManageOrganisation`.
 */
class OrganisationActionController extends Controller
{
    public function suspend(SuspendOrganisationRequest $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $manage->suspend($tenant, $request->validated('reason'), $request->user());

        return $this->back($tenant, 'suspended');
    }

    public function reactivate(Request $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $manage->reactivate($tenant, $request->user());

        return $this->back($tenant, 'reactivated');
    }

    public function changePlan(ChangeOrganisationPlanRequest $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $manage->changePlan($tenant, Plan::ensure(PlanCode::from($request->validated('plan'))), $request->user());

        return $this->back($tenant, 'plan_changed');
    }

    public function extendTrial(ExtendOrganisationTrialRequest $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $endsAt = $request->validated('ends_at');

        // La fin de l'essai est la fin du jour choisi : l'organisation en profite jusqu'au soir.
        $manage->extendTrial($tenant, $endsAt === null ? null : Carbon::parse($endsAt)->endOfDay(), $request->user());

        return $this->back($tenant, 'trial_extended');
    }

    public function scheduleDeletion(ScheduleOrganisationDeletionRequest $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $manage->scheduleDeletion($tenant, $request->validated('request_reference'), $request->user());

        return $this->back($tenant, 'deletion_scheduled');
    }

    public function cancelDeletion(Request $request, Tenant $tenant, ManageOrganisation $manage): RedirectResponse
    {
        $manage->cancelDeletion($tenant, $request->user());

        return $this->back($tenant, 'deletion_cancelled');
    }

    private function back(Tenant $tenant, string $message): RedirectResponse
    {
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("console.organisation.flash.{$message}", ['organisation' => $tenant->name]),
        ]);

        return to_route('console.organisations.show', $tenant->slug);
    }
}
