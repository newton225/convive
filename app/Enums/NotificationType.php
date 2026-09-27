<?php

namespace App\Enums;

/**
 * Les evenements notifiables de README section 5. Catalogue ferme, comme
 * `TenantPermission` : le type arrive dans les preferences et dans les donnees stockees, il ne
 * doit jamais etre une chaine libre.
 */
enum NotificationType: string
{
    case ProofReceived = 'proof_received';
    case HoldsExpired = 'holds_expired';
    case ProofRejected = 'proof_rejected';
    case SeatsExhausted = 'seats_exhausted';
    case RegistrationsPurged = 'registrations_purged';
    case TeamInvitationPending = 'team_invitation_pending';
    case TicketRefused = 'ticket_refused';
    case LargeExport = 'large_export';

    /**
     * Get the permission a member must hold to be told about this type, or null when the alert is
     * addressed to one person rather than to a team (`TeamInvitationPending`).
     *
     * C'est la permission qui decide, pas le nom du profil : un profil remanie par
     * l'organisation continue de recevoir ce qu'il peut traiter.
     */
    public function recipientPermission(): ?TenantPermission
    {
        return match ($this) {
            self::ProofReceived, self::ProofRejected => TenantPermission::ProofsView,
            self::HoldsExpired, self::RegistrationsPurged => TenantPermission::RegistrationsView,
            self::SeatsExhausted => TenantPermission::EventsView,
            self::TicketRefused => TenantPermission::ScanLogView,
            // SECURITY.md M3 : ceux qui surveillent le journal, le Proprietaire en tete.
            self::LargeExport => TenantPermission::AuditView,
            self::TeamInvitationPending => null,
        };
    }

    /**
     * Get the label of this type on the preferences screen.
     */
    public function label(): string
    {
        return __("notifications.preferences.types.{$this->value}");
    }

    /**
     * Get the message of an alert of this type, in the current language.
     *
     * Le texte n'est jamais stocke : seuls le type et ses parametres le sont, pour qu'une alerte
     * s'affiche dans la langue de celui qui la lit, pas de celui qui l'a declenchee.
     *
     * @param  array<string, mixed>  $params
     */
    public function message(array $params): string
    {
        return trans_choice("notifications.types.{$this->value}", (int) ($params['count'] ?? 1), $params);
    }
}
