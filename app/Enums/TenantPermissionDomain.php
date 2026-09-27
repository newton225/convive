<?php

namespace App\Enums;

/**
 * Les modules de l'editeur de profils : un par ecran du back-office, dans l'ordre ou l'exploitant
 * les rencontre. Simple regroupement d'affichage, jamais stocke : changer de module une permission
 * ne modifie aucun droit.
 */
enum TenantPermissionDomain: string
{
    case Events = 'events';
    case Registrations = 'registrations';
    case Proofs = 'proofs';
    case Reconciliation = 'reconciliation';
    case Seating = 'seating';
    case Scan = 'scan';
    case Messages = 'messages';
    case Reports = 'reports';
    case Brand = 'brand';
    case Organisation = 'organisation';
    case PaymentAccounts = 'payment_accounts';
    case Units = 'units';
    case Team = 'team';
    case Profiles = 'profiles';
    case Billing = 'billing';
    case Audit = 'audit';

    /**
     * Get the display label for this domain.
     */
    public function label(): string
    {
        return __("permissions.domains.{$this->value}");
    }

    /**
     * Get the permissions that belong to this domain.
     *
     * @return array<int, TenantPermission>
     */
    public function permissions(): array
    {
        return array_values(array_filter(
            TenantPermission::cases(),
            fn (TenantPermission $permission) => $permission->domain() === $this,
        ));
    }
}
