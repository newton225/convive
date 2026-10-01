export type ConsoleOrganisationStatus =
    | 'trial'
    | 'active'
    | 'past_due'
    | 'suspended'
    | 'deletion_scheduled';

export type ConsoleQuota = {
    used: number;
    max: number | null;
};

export type ConsoleOrganisationSummary = {
    slug: string;
    name: string;
    plan: string;
    planName: string;
    status: ConsoleOrganisationStatus;
    openedAt: string;
    // Null tant que la consommation de l'organisation n'a pas ete relevee.
    lastActivityAt: string | null;
    usage: {
        activeEvents: ConsoleQuota;
        registrations: ConsoleQuota;
        members: ConsoleQuota;
    };
    pastDueSince: string | null;
    suspendedAt: string | null;
    // A l'essai ; `trialEndsAt` nul veut alors dire « sans date de fin ».
    onTrial: boolean;
    trialEndsAt: string | null;
    deletionAt: string | null;
};

export type ConsoleOrganisationDetails = ConsoleOrganisationSummary & {
    legalName: string;
    contactEmail: string | null;
    subdomain: string | null;
    // Le motif d'une suspension manuelle en cours, et si c'est bien l'editeur qui a suspendu :
    // c'est la seule suspension qu'il leve depuis la console.
    suspensionReason: string | null;
    suspendedByEditor: boolean;
    // Un abonnement l'emporte sur l'essai : on n'en offre pas a une organisation abonnee.
    hasSubscription: boolean;
    history: {
        at: string;
        type: 'opened' | 'plan_changed';
        detail: string | null;
    }[];
    invoices: {
        number: string;
        amount: number;
        currency: string;
        status: string;
        issuedAt: string;
    }[];
    supportAccess: {
        operator: string;
        grantedBy: string | null;
        expiresAt: string;
    } | null;
    consoleActions: {
        at: string;
        actor: string | null;
        type: ConsoleAuditType;
    }[];
};

export type ConsoleUnpaid = {
    slug: string;
    name: string;
    planName: string;
    amount: number;
    currency: string;
    status: ConsoleOrganisationStatus;
    pastDueSince: string | null;
    remindersSent: number;
    suspendsAt: string | null;
    failureReason: string;
};

export type ConsoleFailedPayment = {
    slug: string;
    name: string;
    at: string;
    amount: number;
    currency: string;
    reason: string;
};

export type ConsolePlanOption = {
    code: string;
    name: string;
};

export type ConsolePlan = {
    code: string;
    name: string;
    monthlyPrice: number | null;
    monthlyPriceEur: number | null;
    monthlyPriceUsd: number | null;
    maxActiveEvents: number | null;
    maxRegistrations: number | null;
    maxMembers: number | null;
    hasReconciliation: boolean;
    hasReports: boolean;
};

export type ConsoleDatabaseIssue = {
    slug: string;
    name: string;
    issue: 'missing_database' | 'pending_migrations';
    pendingMigrations: number | null;
};

export type ConsoleScheduledTask = {
    key: string;
    lastRunAt: string;
    everyMinutes: number;
    late: boolean;
};

export type ConsoleQueue = {
    name: string;
    pending: number;
    failed: number;
    oldestAt: string | null;
};

export type ConsoleBackup = {
    lastAt: string;
    sizeMb: number;
    healthy: boolean;
};

export type ConsoleAnnouncement = {
    id: number;
    eventName: string;
    organisationName: string;
    announcedAt: string;
    startsAt: string;
    publicUrl: string;
};

export type ConsoleWithdrawnAnnouncement = {
    id: number;
    eventName: string;
    organisationName: string;
    withdrawnAt: string;
    actor: string;
    reason: string;
};

export type ConsoleAuditType =
    | 'support_access_used'
    | 'trial_extended'
    | 'tenant_suspended'
    | 'tenant_reactivated'
    | 'deletion_scheduled'
    | 'plan_changed'
    | 'announcement_withdrawn'
    | 'operator_invited'
    | 'operator_removed'
    | 'plan_updated'
    | 'database_repaired'
    | 'deletion_cancelled'
    | 'tenant_erased';

export type ConsoleAuditEntry = {
    id: number;
    at: string;
    actor: string | null;
    type: ConsoleAuditType;
    organisation: string | null;
    ip: string | null;
};

export type ConsoleOperatorProfile = 'founder' | 'support' | 'accounting';

export type ConsoleOperator = {
    id: number;
    // Null pour un Fondateur de depart, defini dans la configuration et non dans l'equipe en base.
    operatorId: number | null;
    name: string;
    email: string;
    profile: ConsoleOperatorProfile;
    twoFactor: boolean;
    lastLoginAt: string | null;
    removable: boolean;
};

export type ConsoleOperatorInvitation = {
    id: number;
    email: string;
    profile: ConsoleOperatorProfile;
    sentAt: string;
};
