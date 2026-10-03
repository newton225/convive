export type DashboardKpis = {
    registrations: number;
    validated: number;
    toCheck: number;
    withoutProof: number;
    seatsLeft: number;
};

export type DashboardHoldExpiry = {
    lapsed: number;
    holds: number;
    rate: number | null;
};

export type DashboardDayCount = { date: string; count: number };

export type DashboardChannelCount = { channel: string; count: number };

export type DashboardTableOccupancy = {
    number: number;
    seated: number;
    capacity: number;
};

export type DashboardActivityType =
    | 'proof_received'
    | 'proof_approved'
    | 'proof_rejected'
    | 'registration_cancelled'
    | 'hold_expired'
    | 'scan_refused';

export type DashboardActivity = {
    id: string;
    type: DashboardActivityType;
    name: string | null;
    at: string;
};

export type DashboardContext = {
    registrationsThisWeek: number;
    collectedAmount: number;
    validatedShare: number | null;
    waitingOver24h: number;
    purgeAt: string | null;
    daysUntilEvent: number | null;
};

// Un evenement propose par le selecteur du tableau de bord.
export type DashboardEventChoice = {
    id: number;
    name: string;
    startsAt: string | null;
};

export type DashboardOverview = {
    eventId: number;
    eventName: string;
    capacity: number;
    kpis: DashboardKpis;
    context: DashboardContext;
    holdExpiry: DashboardHoldExpiry;
    registrationsPerDay: DashboardDayCount[];
    proofsByChannel: DashboardChannelCount[];
    tableOccupancy: DashboardTableOccupancy[];
    recentActivity: DashboardActivity[];
    // Annulations dont le paiement reste a rendre (README 2.11), null s'il n'y en a aucune.
    refundsDue: { count: number; amount: number } | null;
};

// La carte « Premiers pas » (`App\Support\GettingStarted`), null une fois tout fait.
export type GettingStartedStepKey =
    | 'identity'
    | 'payment_account'
    | 'event'
    | 'publish'
    | 'team';

export type GettingStarted = {
    steps: { key: GettingStartedStepKey; done: boolean }[];
    completed: number;
};
