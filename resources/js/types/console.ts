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
    lastActivityAt: string;
    usage: {
        activeEvents: ConsoleQuota;
        registrations: ConsoleQuota;
        members: ConsoleQuota;
    };
    pastDueSince: string | null;
    suspendedAt: string | null;
    trialEndsAt: string | null;
    deletionAt: string | null;
};

export type ConsoleOrganisationDetails = ConsoleOrganisationSummary & {
    legalName: string;
    contactEmail: string;
    subdomain: string;
    history: { at: string; type: 'opened' | 'plan_changed'; detail: string }[];
    invoices: {
        number: string;
        amount: number;
        currency: string;
        status: string;
        issuedAt: string;
    }[];
    supportAccess: {
        operator: string;
        grantedBy: string;
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
    | 'operator_invited';

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
    name: string;
    email: string;
    profile: ConsoleOperatorProfile;
    twoFactor: boolean;
    lastLoginAt: string | null;
};

export type ConsoleOperatorInvitation = {
    email: string;
    profile: ConsoleOperatorProfile;
    sentAt: string;
};
