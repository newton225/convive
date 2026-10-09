<?php

namespace App\Enums;

/**
 * Le catalogue ferme des descriptions d'entree du journal d'audit (README ecran 23), une entree
 * par appel `activity()->...->log(...)` dans l'application. Sert a proposer le filtre de type a
 * l'ecran avec la liste complete des types possibles, pas seulement ceux dejq apparus sur la
 * page courante.
 *
 * Chaque valeur correspond au texte passe a `log()`. On en ajoute quand un nouvel appel
 * `activity()->log(...)` apparait dans le code ; on n'en retire jamais sans verifier qu'aucune
 * entree existante ne l'utilise plus (le journal est en ecriture seule, CLAUDE.md « Securite »).
 */
enum AuditLogType: string
{
    case OrganisationLegalUpdated = 'organisation.legal_updated';
    case OrganisationBrandUpdated = 'organisation.brand_updated';
    case OrganisationSubdomainUpdated = 'organisation.subdomain_updated';
    case OrganisationBrandFileUpdated = 'organisation.brand_file_updated';
    case OrganisationBrandFileDeleted = 'organisation.brand_file_deleted';
    case OrganisationTicketTemplateUpdated = 'organisation.ticket_template_updated';

    case EventCreated = 'event.created';
    case EventUpdated = 'event.updated';
    case EventPublished = 'event.published';
    case EventClosed = 'event.closed';
    case EventDuplicated = 'event.duplicated';
    case EventDeleted = 'event.deleted';
    case EventVisualUpdated = 'event.visual_updated';
    case EventVisualDeleted = 'event.visual_deleted';

    case ProofsValidated = 'proofs.validated';
    case ProofsRejected = 'proofs.rejected';

    case RegistrationsPurged = 'registrations.purged';
    case RegistrationsCancelled = 'registrations.cancelled';
    case RegistrationsRefunded = 'registrations.refunded';
    case RegistrationsClaimResolved = 'registrations.claim_resolved';
    case RegistrationsCardSent = 'registrations.card_sent';
    case RegistrationsCardShared = 'registrations.card_shared';

    case ReconciliationResolved = 'reconciliation.resolved';
    case ReconciliationImported = 'reconciliation.imported';

    case SeatingConstraintCreated = 'seating.constraint_created';
    case SeatingConstraintDeleted = 'seating.constraint_deleted';
    case SeatingMoved = 'seating.moved';
    case SeatingTableResized = 'seating.table_resized';

    case ProfileCreated = 'profile.created';
    case ProfileUpdated = 'profile.updated';
    case ProfileDeleted = 'profile.deleted';

    case PaymentAccountUpdated = 'payment_account.updated';
    case PaymentAccountChangeRequested = 'payment_account.change_requested';
    case PaymentAccountChangeApproved = 'payment_account.change_approved';
    case PaymentAccountChangeCancelled = 'payment_account.change_cancelled';
    case PaymentAccountDeleted = 'payment_account.deleted';

    case UnitCreated = 'unit.created';
    case UnitUpdated = 'unit.updated';
    case UnitDeleted = 'unit.deleted';

    case TenantMemberProfileAssigned = 'tenant_member.profile_assigned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type) => $type->value, self::cases());
    }
}
