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
    case EventsAnnounce = 'events.announce';

    case RegistrationsView = 'registrations.view';
    case RegistrationsExport = 'registrations.export';
    case RegistrationsPurge = 'registrations.purge';
    case RegistrationsCancel = 'registrations.cancel';
    // Sort du paiement d'une inscription annulee (README 2.11) : une decision d'argent,
    // distincte de l'annulation elle-meme.
    case RegistrationsRefund = 'registrations.refund';

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
    case ScanManual = 'scan.manual';
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
     * Get what an open support access lets a member of the Convive team do (README section 3) :
     * consulter, jamais agir. Ni export, ni comptes de versement, ni reglages : lire le contenu
     * suffit a aider, et rien de plus ne doit sortir de l'organisation.
     *
     * @return array<int, self>
     */
    public static function supportReadable(): array
    {
        return [
            self::EventsView, self::RegistrationsView, self::ProofsView, self::SeatingView,
            self::ScanLogView, self::ReportsView, self::TeamView, self::AuditView,
        ];
    }

    /**
     * Get the domain this permission is grouped under in the interface.
     */
    public function domain(): TenantPermissionDomain
    {
        return match ($this) {
            self::EventsView, self::EventsCreate, self::EventsUpdate,
            self::EventsDuplicate, self::EventsClose,
            self::EventsAnnounce => TenantPermissionDomain::Events,

            self::RegistrationsView, self::RegistrationsExport,
            self::RegistrationsPurge, self::RegistrationsCancel,
            self::RegistrationsRefund => TenantPermissionDomain::Registrations,

            self::ProofsView, self::ProofsApprove,
            self::ProofsReject => TenantPermissionDomain::Proofs,

            self::ReconciliationImport,
            self::ReconciliationResolve => TenantPermissionDomain::Reconciliation,

            self::SeatingView, self::SeatingAssign,
            self::SeatingConstraints => TenantPermissionDomain::Seating,

            self::ScanPerform, self::ScanForce, self::ScanManual,
            self::ScanLogView => TenantPermissionDomain::Scan,

            self::MessagesSchedule, self::MessagesSend,
            self::MessagesTemplates => TenantPermissionDomain::Messages,

            self::ReportsView, self::ReportsExport => TenantPermissionDomain::Reports,

            // La marque gouverne aussi le gabarit du billet (`TicketTemplateController`).
            self::TenantBranding => TenantPermissionDomain::Brand,
            self::TenantLegal, self::TenantDomain => TenantPermissionDomain::Organisation,
            self::TenantPaymentAccounts => TenantPermissionDomain::PaymentAccounts,
            self::TenantUnits => TenantPermissionDomain::Units,

            self::TeamView, self::TeamInvite, self::TeamRemove => TenantPermissionDomain::Team,
            self::ProfilesManage => TenantPermissionDomain::Profiles,

            self::BillingView, self::BillingManage => TenantPermissionDomain::Billing,

            self::AuditView => TenantPermissionDomain::Audit,
        };
    }

    /**
     * Get the permission without which this one is unusable, if any : on ne modifie pas un
     * evenement qu'on ne peut pas voir. Sert a l'editeur de profils, qui coche la consultation en
     * meme temps qu'une action du module.
     */
    public function prerequisite(): ?self
    {
        return match ($this) {
            self::EventsCreate, self::EventsUpdate, self::EventsDuplicate,
            self::EventsClose, self::EventsAnnounce => self::EventsView,

            self::RegistrationsExport, self::RegistrationsPurge,
            self::RegistrationsCancel, self::RegistrationsRefund => self::RegistrationsView,

            self::ProofsApprove, self::ProofsReject => self::ProofsView,

            self::SeatingAssign, self::SeatingConstraints => self::SeatingView,

            self::ReportsExport => self::ReportsView,

            self::TeamInvite, self::TeamRemove => self::TeamView,

            self::BillingManage => self::BillingView,

            default => null,
        };
    }

    /**
     * Get the short action label shown inside its module (« Creer », « Exporter »...).
     */
    public function actionLabel(): string
    {
        return $this->translated('actions');
    }

    /**
     * Get the display label for this permission.
     */
    public function label(): string
    {
        return $this->translated('items');
    }

    /**
     * Read this permission's line in the given group of `lang/{locale}/permissions.php`.
     *
     * Pas `__("permissions.items.{$this->value}")` : la valeur contient un point (`events.view`),
     * que le traducteur lit comme un niveau d'imbrication de plus, et la cle brute s'affichait a
     * la place du libelle. On charge le groupe, puis on lit la cle telle quelle.
     */
    private function translated(string $group): string
    {
        $lines = __("permissions.{$group}");
        $line = is_array($lines) ? ($lines[$this->value] ?? null) : null;

        return is_string($line) ? $line : $this->value;
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
