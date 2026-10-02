export type ConsoleOrganisationStatus =
    | 'trial'
    | 'active'
    | 'past_due'
    | 'suspended'
    | 'deletion_scheduled'
    // Supprimee par son Proprietaire : restaurable jusqu'a son effacement.
    | 'deleted_by_owner';

export type ConsoleQuota = {
    used: number;
    max: number | null;
};

export type ConsoleQuotaName =
    | 'max_active_events'
    | 'max_registrations'
    | 'max_members'
    | 'max_messages_per_month';

// Pour chaque limite : celle reglee pour l'organisation seule (null : aucune) et celle de son plan
// (null : sans limite).
export type ConsoleOrganisationLimits = Record<
    ConsoleQuotaName,
    { own: number | null; plan: number | null }
>;

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
    limits: ConsoleOrganisationLimits;
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
        reason: string | null;
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
};

// Ce qui est du, par devise : un total qui melangerait des devises ne dirait rien.
export type ConsoleAmountDue = {
    currency: string;
    amount: number;
};

// Les revenus de l'editeur, par devise.
export type ConsoleRevenue = {
    recurring: ConsoleAmountDue[];
    subscribers: number;
    byPlan: { plan: string; count: number }[];
    collectedThisMonth: ConsoleAmountDue[];
    collectedLastMonth: ConsoleAmountDue[];
};

export type ConsoleFailedPayment = {
    id: number;
    slug: string;
    name: string;
    at: string;
    amount: number;
    currency: string;
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
    maxMessagesPerMonth: number | null;
    hasReconciliation: boolean;
    hasReports: boolean;
};

export type ConsoleDatabaseIssue = {
    slug: string;
    name: string;
    issue: 'missing_database' | 'pending_migrations';
    pendingMigrations: number | null;
};

// Un controle de sante rejoue a l'affichage de l'ecran (`spatie/laravel-health`).
export type ConsoleHealthCheck = {
    name: string;
    status: 'ok' | 'warning' | 'failed' | 'crashed' | 'skipped';
    summary: string;
};

// Une tache planifiee telle que le planificateur l'a relevee. `label` arrive deja traduit.
export type ConsoleScheduledTask = {
    name: string;
    label: string;
    frequency: {
        kind: 'minutes' | 'hourly' | 'daily' | 'other';
        minutes: number | null;
        at: string | null;
        expression: string;
    };
    lastRunAt: string | null;
    runtimeMs: number | null;
    state: 'ok' | 'late' | 'failed' | 'waiting';
    failure: string | null;
};

export type ConsoleFailedJob = {
    id: string;
    job: string;
    queue: string;
    failedAt: string;
    error: string;
};

// `pending` est nul quand la file ne repond pas.
export type ConsoleQueue = {
    pending: number | null;
    failedCount: number;
    failed: ConsoleFailedJob[];
};

// Un canal d'envoi : `simulated` est vrai quand il n'envoie pas encore reellement.
export type ConsoleMessageChannel = {
    channel: 'mail' | 'whatsapp';
    simulated: boolean;
    lastDay: number;
    lastWeek: number;
};

export type ConsoleMessageType = {
    type: string;
    label: string;
    count: number;
};

// Un envoi releve : jamais son contenu, et un destinataire masque.
export type ConsoleMessage = {
    id: number;
    at: string;
    channel: 'mail' | 'whatsapp';
    type: string;
    organisation: string | null;
    recipient: string | null;
    simulated: boolean;
};

// Un compte tel que la console le montre : jamais son mot de passe ni le contenu de ses
// organisations.
export type ConsoleAccount = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    createdAt: string | null;
    hasTwoFactor: boolean;
    organisations: string[];
    blockedAt: string | null;
    blockedReason: string | null;
};

// L'integrite des journaux d'audit, verifiee chaque nuit. `checkedAt` est nul tant que la
// verification n'a jamais tourne.
export type ConsoleAuditChains = {
    checkedAt: string | null;
    count: number;
    broken: {
        scope: string;
        organisation: string | null;
        entryId: number;
        checkedAt: string;
    }[];
};

// Une limite de debit atteinte ou une connexion verrouillee.
export type ConsoleSecurityEvent = {
    id: number;
    at: string;
    type: 'rate_limited' | 'login_lockout';
    subject: string | null;
    ip: string | null;
};

export type ConsoleBackup = {
    healthy: boolean;
    lastAt: string | null;
    lastSizeBytes: number;
    count: number;
    totalSizeBytes: number;
    onApplicationServer: boolean;
    encrypted: boolean;
};

export type ConsoleAnnouncement = {
    id: number;
    eventName: string;
    organisationName: string;
    announcedAt: string;
    startsAt: string | null;
    publicUrl: string;
};

export type ConsoleWithdrawnAnnouncement = {
    id: number;
    eventName: string;
    organisationName: string;
    withdrawnAt: string;
    actor: string | null;
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
    | 'tenant_erased'
    | 'payment_reminder_sent'
    | 'support_access_finished'
    | 'backup_run'
    | 'support_access_requested'
    | 'support_access_request_taken'
    | 'failed_job_retried'
    | 'failed_job_forgotten'
    | 'tenant_deleted_by_owner'
    | 'tenant_restored'
    | 'account_blocked'
    | 'account_unblocked'
    | 'two_factor_reset';

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
