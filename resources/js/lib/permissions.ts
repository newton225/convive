import type { TenantPermissions } from '@/types';

/**
 * Les valeurs du catalogue referencees par l'interface. Le catalogue fait foi cote serveur
 * (App\Enums\TenantPermission) ; on ne declare ici que ce que le front doit masquer.
 */
export const Permission = {
    EventsView: 'events.view',
    EventsCreate: 'events.create',
    EventsUpdate: 'events.update',
    EventsDuplicate: 'events.duplicate',
    EventsClose: 'events.close',
    TenantBranding: 'tenant.branding',
    TenantLegal: 'tenant.legal',
    TenantDomain: 'tenant.domain',
    TenantUnits: 'tenant.units',
    TenantPaymentAccounts: 'tenant.payment_accounts',
    ProofsView: 'proofs.view',
    ProofsApprove: 'proofs.approve',
    ProofsReject: 'proofs.reject',
    SeatingView: 'seating.view',
    SeatingAssign: 'seating.assign',
    ScanPerform: 'scan.perform',
    ScanForce: 'scan.force',
    ScanLogView: 'scan.log.view',
    TeamView: 'team.view',
    TeamInvite: 'team.invite',
    TeamRemove: 'team.remove',
    ProfilesManage: 'profiles.manage',
    RegistrationsView: 'registrations.view',
    RegistrationsExport: 'registrations.export',
    RegistrationsPurge: 'registrations.purge',
    RegistrationsCancel: 'registrations.cancel',
    RegistrationsRefund: 'registrations.refund',
    ReconciliationImport: 'reconciliation.import',
    ReconciliationResolve: 'reconciliation.resolve',
    ReportsView: 'reports.view',
    ReportsExport: 'reports.export',
    BillingView: 'billing.view',
    BillingManage: 'billing.manage',
    AuditView: 'audit.view',
} as const;

export type PermissionValue = (typeof Permission)[keyof typeof Permission];

/**
 * Masque ce qui est interdit. Ce n'est jamais un controle d'acces : chaque route et chaque
 * action revalident cote serveur.
 */
export function can(
    permissions: TenantPermissions,
    permission: PermissionValue,
): boolean {
    return permissions.values.includes(permission);
}
