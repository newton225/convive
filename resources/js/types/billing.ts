export type BillingPlan = {
    code: string;
    name: string;
    prices: Record<string, number | null>;
    maxActiveEvents: number | null;
    maxRegistrations: number | null;
    maxMembers: number | null;
    hasReconciliation: boolean;
    hasReports: boolean;
    hasCustomDomain: boolean;
    hasSso: boolean;
    current: boolean;
};

export type BillingSubscription = {
    status: 'active' | 'past_due' | 'suspended' | 'canceled';
    statusLabel: string;
    paymentMethod: { brand: string | null; last4: string } | null;
    hasProviderCustomer: boolean;
    currentPeriodEndsAt: string | null;
    pastDueSince: string | null;
    suspendedAt: string | null;
    canceledAt: string | null;
};

export type BillingQuota = {
    used: number;
    max: number | null;
};

export type BillingUsage = {
    events: BillingQuota;
    registrations: BillingQuota;
    members: BillingQuota;
};

export type BillingInvoice = {
    id: number;
    number: string;
    amount: number;
    currency: string;
    status: 'open' | 'paid' | 'failed' | 'void';
    statusLabel: string;
    issuedAt: string;
    hostedUrl: string | null;
};
