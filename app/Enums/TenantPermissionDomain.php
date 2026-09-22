<?php

namespace App\Enums;

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
    case Tenant = 'tenant';
    case Team = 'team';
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
