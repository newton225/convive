<?php

namespace App\Actions\Tenants;

use App\Models\ShowcaseEvent;
use App\Models\Tenant;
use App\Models\TenantBranding;
use Illuminate\Support\Facades\DB;

/**
 * Enregistre l'identite legale, la marque et le sous-domaine d'une organisation.
 *
 * Les trois passent par le journal : l'identite legale et le compte de versement sont les
 * deux endroits ou une modification discrete detourne l'argent, et le sous-domaine est
 * l'adresse que les invites reconnaissent.
 */
class SaveTenantOrganisation
{
    /**
     * Save the legal identity of the tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function legalIdentity(Tenant $tenant, array $attributes): TenantBranding
    {
        return $this->save($tenant, $attributes, 'organisation.legal_updated');
    }

    /**
     * Save the brand of the tenant.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function brand(Tenant $tenant, array $attributes): TenantBranding
    {
        return $this->save($tenant, $attributes, 'organisation.brand_updated');
    }

    /**
     * Save the ticket template (README ecran 15) : model and activatable elements.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function ticketTemplate(Tenant $tenant, array $attributes): TenantBranding
    {
        return $this->save($tenant, $attributes, 'organisation.ticket_template_updated');
    }

    /**
     * Save the subdomain of the tenant.
     */
    public function subdomain(Tenant $tenant, string $subdomain): Tenant
    {
        return DB::transaction(function () use ($tenant, $subdomain) {
            $before = $tenant->subdomain;

            $tenant->update(['subdomain' => $subdomain]);

            activity()
                ->performedOn($tenant)
                ->event('updated')
                ->withProperties([
                    'old' => ['subdomain' => $before],
                    'attributes' => ['subdomain' => $tenant->subdomain],
                    'tenant_id' => $tenant->id,
                ])
                ->log('organisation.subdomain_updated');

            return $tenant;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function save(Tenant $tenant, array $attributes, string $log): TenantBranding
    {
        return DB::transaction(function () use ($tenant, $attributes, $log) {
            $branding = $tenant->brandingOrCreate();

            $before = $branding->only(array_keys($attributes));

            $branding->fill($attributes);
            $branding->save();

            activity()
                ->performedOn($branding)
                ->event('updated')
                ->withProperties([
                    'old' => $before,
                    'attributes' => $branding->only(array_keys($attributes)),
                    'tenant_id' => $tenant->id,
                ])
                ->log($log);

            // La vitrine garde une copie du nom affiche (table centrale, lue sans ouvrir les bases) :
            // elle suit le nom de l'organisation, sinon une carte annoncee garderait l'ancien.
            if ($branding->wasChanged('display_name')) {
                ShowcaseEvent::where('tenant_id', $tenant->id)
                    ->update(['organisation_name' => $branding->display_name ?? $tenant->name]);
            }

            return $branding;
        });
    }
}
