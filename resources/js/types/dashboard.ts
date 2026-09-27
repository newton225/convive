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
};
