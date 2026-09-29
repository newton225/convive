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

// Un evenement de la vitrine publique, lu dans la table centrale (jamais dans la base d'un locataire).
export type ShowcaseEvent = {
    name: string;
    organisationName: string;
    startsAt: string | null;
    publicUrl: string;
    visualUrl: string | null;
};
