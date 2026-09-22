export type SitePlan = {
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
    highlighted: boolean;
};
