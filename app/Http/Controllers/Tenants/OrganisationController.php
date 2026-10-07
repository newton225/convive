<?php

namespace App\Http\Controllers\Tenants;

use App\Actions\Tenants\SaveTenantBrandFile;
use App\Actions\Tenants\SaveTenantOrganisation;
use App\Enums\BrandFile;
use App\Enums\LegalForm;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenants\SaveBrandFileRequest;
use App\Http\Requests\Tenants\SaveBrandRequest;
use App\Http\Requests\Tenants\SaveLegalIdentityRequest;
use App\Http\Requests\Tenants\SaveSubdomainRequest;
use App\Models\Tenant;
use App\Models\TenantBranding;
use App\Support\GettingStarted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    /**
     * Show the organisation form: legal identity, brand and subdomain.
     */
    public function edit(Request $request, Tenant $tenant): Response
    {
        $branding = $tenant->branding;

        return Inertia::render('tenants/organisation', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'subdomain' => $tenant->subdomain,
                'isPersonal' => $tenant->is_personal,
                'isReadyToPublish' => $tenant->isReadyToPublish(),
                // Ce qui manque encore pour publier, sous-domaine compris, et ce qui est exige :
                // l'identite legale s'enregistre par morceaux, la page dit donc lesquels comptent
                // pour publier sans les rendre obligatoires a la saisie (decision du 2026-10-07).
                'missingBeforePublishing' => [
                    ...($branding?->missingBeforePublishing() ?? TenantBranding::RequiredToPublish),
                    ...($tenant->subdomain === null ? ['subdomain'] : []),
                ],
                'requiredToPublish' => TenantBranding::RequiredToPublish,
            ],
            'branding' => [
                'displayName' => $branding?->display_name,
                'legalName' => $branding?->legal_name,
                'legalForm' => $branding?->legal_form?->value,
                'representativeName' => $branding?->representative_name,
                'registrationNumber' => $branding?->registration_number,
                'taxNumber' => $branding?->tax_number,
                'address' => $branding?->address,
                'city' => $branding?->city,
                'country' => $branding?->country,
                'email' => $branding?->email,
                'phone' => $branding?->phone,
                'colors' => ($branding ?? $tenant->branding()->make())->colors(),
                'files' => $this->brandFiles($tenant),
            ],
            'brandFiles' => array_map(fn (BrandFile $file) => [
                'value' => $file->value,
                'label' => $file->label(),
                'hint' => $file->hint(),
            ], BrandFile::organisationCases()),
            'legalForms' => array_map(fn (LegalForm $form) => [
                'value' => $form->value,
                'label' => $form->label(),
            ], LegalForm::cases()),
            'permissions' => $request->user()->toTenantPermissions($tenant),
        ]);
    }

    /**
     * Update the legal identity of the organisation.
     */
    public function updateLegalIdentity(SaveLegalIdentityRequest $request, Tenant $tenant, SaveTenantOrganisation $save): RedirectResponse
    {
        $save->legalIdentity($tenant, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.legal_updated')]);

        return GettingStarted::redirect($request, $tenant, to_route('tenants.organisation.edit', $tenant));
    }

    /**
     * Update the brand of the organisation.
     */
    public function updateBrand(SaveBrandRequest $request, Tenant $tenant, SaveTenantOrganisation $save): RedirectResponse
    {
        $save->brand($tenant, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.brand_updated')]);

        return to_route('tenants.organisation.edit', $tenant);
    }

    /**
     * Store one of the brand files.
     */
    public function storeFile(SaveBrandFileRequest $request, Tenant $tenant, string $file, SaveTenantBrandFile $save): RedirectResponse
    {
        $save->store($tenant, $this->brandFile($file), $request->file('file'), $request->crop());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.file_updated')]);

        // Retour a la page d'ou vient le depot : l'organisation, ou le gabarit du billet pour son
        // fond (`BrandFile::TicketBackground`).
        return redirect()->back(fallback: route('tenants.organisation.edit', $tenant));
    }

    /**
     * Remove one of the brand files.
     */
    public function destroyFile(Tenant $tenant, string $file, SaveTenantBrandFile $save): RedirectResponse
    {
        Gate::authorize('updateBrand', $tenant);

        $save->delete($tenant, $this->brandFile($file));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.file_deleted')]);

        // Retour a la page d'ou vient le depot : l'organisation, ou le gabarit du billet pour son
        // fond (`BrandFile::TicketBackground`).
        return redirect()->back(fallback: route('tenants.organisation.edit', $tenant));
    }

    /**
     * Update the subdomain of the organisation.
     */
    public function updateSubdomain(SaveSubdomainRequest $request, Tenant $tenant, SaveTenantOrganisation $save): RedirectResponse
    {
        $save->subdomain($tenant, $request->validated('subdomain'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('organisation.flash.subdomain_updated')]);

        return GettingStarted::redirect($request, $tenant, to_route('tenants.organisation.edit', $tenant));
    }

    /**
     * Le nom de collection arrive par l'URL : il doit venir du catalogue, jamais d'une chaine
     * libre, sinon on ecrit dans une collection arbitraire.
     */
    private function brandFile(string $file): BrandFile
    {
        return BrandFile::tryFrom($file) ?? abort(404);
    }

    /**
     * @return array<string, string|null>
     */
    private function brandFiles(Tenant $tenant): array
    {
        $branding = $tenant->branding;

        return collect(BrandFile::cases())
            ->mapWithKeys(fn (BrandFile $file) => [
                $file->value => $branding?->brandFileUrl($file),
            ])
            ->all();
    }
}
