<?php

namespace App\Enums;

/**
 * Catalogue ferme des permissions. Chacune correspond a un point de controle reel dans
 * l'application. L'exploitant compose ses profils avec ce catalogue, il n'en invente pas :
 * une permission creee depuis l'interface rendrait le controle d'acces falsifiable par saisie.
 * On ajoute une entree quand un ecran l'exige, on n'en retire jamais sans migration.
 */
enum TenantPermission: string
{
    case EventsView = 'events.view';
    case EventsCreate = 'events.create';
    case EventsUpdate = 'events.update';
    case EventsDuplicate = 'events.duplicate';
    case EventsClose = 'events.close';

    case RegistrationsView = 'registrations.view';
    case RegistrationsExport = 'registrations.export';
    case RegistrationsPurge = 'registrations.purge';
    case RegistrationsCancel = 'registrations.cancel';

    case ProofsView = 'proofs.view';
    case ProofsApprove = 'proofs.approve';
    case ProofsReject = 'proofs.reject';

    case ReconciliationImport = 'reconciliation.import';
    case ReconciliationResolve = 'reconciliation.resolve';

    case SeatingView = 'seating.view';
    case SeatingAssign = 'seating.assign';
    case SeatingConstraints = 'seating.constraints';

    case ScanPerform = 'scan.perform';
    case ScanForce = 'scan.force';
    case ScanLogView = 'scan.log.view';

    case MessagesSchedule = 'messages.schedule';
    case MessagesSend = 'messages.send';
    case MessagesTemplates = 'messages.templates';

    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    case TenantBranding = 'tenant.branding';
    case TenantLegal = 'tenant.legal';
    case TenantDomain = 'tenant.domain';
    case TenantPaymentAccounts = 'tenant.payment_accounts';
    case TenantUnits = 'tenant.units';

    case TeamView = 'team.view';
    case TeamInvite = 'team.invite';
    case TeamRemove = 'team.remove';
    case ProfilesManage = 'profiles.manage';

    case BillingView = 'billing.view';
    case BillingManage = 'billing.manage';

    case AuditView = 'audit.view';

    /**
     * Get the domain this permission is grouped under in the interface.
     */
    public function domain(): TenantPermissionDomain
    {
        return match ($this) {
            self::EventsView, self::EventsCreate, self::EventsUpdate,
            self::EventsDuplicate, self::EventsClose => TenantPermissionDomain::Events,

            self::RegistrationsView, self::RegistrationsExport,
            self::RegistrationsPurge, self::RegistrationsCancel => TenantPermissionDomain::Registrations,

            self::ProofsView, self::ProofsApprove,
            self::ProofsReject => TenantPermissionDomain::Proofs,

            self::ReconciliationImport,
            self::ReconciliationResolve => TenantPermissionDomain::Reconciliation,

            self::SeatingView, self::SeatingAssign,
            self::SeatingConstraints => TenantPermissionDomain::Seating,

            self::ScanPerform, self::ScanForce,
            self::ScanLogView => TenantPermissionDomain::Scan,

            self::MessagesSchedule, self::MessagesSend,
            self::MessagesTemplates => TenantPermissionDomain::Messages,

            self::ReportsView, self::ReportsExport => TenantPermissionDomain::Reports,

            self::TenantBranding, self::TenantLegal, self::TenantDomain,
            self::TenantPaymentAccounts, self::TenantUnits => TenantPermissionDomain::Tenant,

            self::TeamView, self::TeamInvite, self::TeamRemove,
            self::ProfilesManage => TenantPermissionDomain::Team,

            self::BillingView, self::BillingManage => TenantPermissionDomain::Billing,

            self::AuditView => TenantPermissionDomain::Audit,
        };
    }

    /**
     * Get the display label for this permission.
     */
    public function label(): string
    {
        return __("permissions.items.{$this->value}");
    }

    /**
     * Get every permission value in the catalogue.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
