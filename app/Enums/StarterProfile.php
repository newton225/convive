<?php

namespace App\Enums;

/**
 * Les profils de base crees a l'ouverture d'un espace, a cote du Proprietaire (decision du
 * proprietaire du projet, 2026-10-07) : ni modifiables ni supprimables, pour qu'un meme nom veuille
 * dire la meme chose dans toutes les organisations. Une organisation qui veut une variante cree son
 * propre profil, et peut masquer celui-ci.
 *
 * La valeur est rangee dans `profiles.starter` : c'est elle, pas le nom, qui reconnait un profil de
 * base.
 */
enum StarterProfile: string
{
    case Treasurer = 'treasurer';
    case Host = 'host';
    case Reader = 'reader';

    /**
     * Get the name the profile is created with.
     */
    public function profileName(): string
    {
        return match ($this) {
            self::Treasurer => 'Tresorier',
            self::Host => 'Hotesse',
            self::Reader => 'Lecture',
        };
    }

    /**
     * Get the description shown to the operator.
     */
    public function description(): string
    {
        return __("profiles.starters.{$this->value}");
    }

    /**
     * Determine whether carriers of the profile must have two-factor authentication.
     *
     * Le Tresorier touche a l'argent : preuves de paiement, rapprochement, remboursements.
     */
    public function requiresTwoFactor(): bool
    {
        return $this === self::Treasurer;
    }

    /**
     * @return array<int, TenantPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Treasurer => [
                TenantPermission::EventsView,
                TenantPermission::RegistrationsView,
                TenantPermission::RegistrationsExport,
                TenantPermission::RegistrationsRefund,
                TenantPermission::RegistrationsClaims,
                TenantPermission::ProofsView,
                TenantPermission::ProofsApprove,
                TenantPermission::ProofsReject,
                TenantPermission::ReconciliationImport,
                TenantPermission::ReconciliationResolve,
                TenantPermission::ReportsView,
                TenantPermission::ReportsExport,
            ],
            // Le forcage d'entree fait partie du poste d'accueil : il est autorise, et journalise.
            // Pas l'entree sans scan (`scan.manual`) : faire entrer quelqu'un sur son seul nom est
            // une decision de responsable, a lui de la confier a un profil qu'il cree.
            self::Host => [
                TenantPermission::EventsView,
                TenantPermission::ScanPerform,
                TenantPermission::ScanForce,
                TenantPermission::ScanLogView,
            ],
            self::Reader => [
                TenantPermission::EventsView,
                TenantPermission::RegistrationsView,
                TenantPermission::ReportsView,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(fn (TenantPermission $permission) => $permission->value, $this->permissions());
    }
}
