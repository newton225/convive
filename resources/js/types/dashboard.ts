export type DashboardKpis = {
    registrations: number;
    validated: number;
    toCheck: number;
    withoutProof: number;
    seatsLeft: number;
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

export type DashboardOverview = {
    eventName: string;
    capacity: number;
    kpis: DashboardKpis;
    registrationsPerDay: DashboardDayCount[];
    proofsByChannel: DashboardChannelCount[];
    tableOccupancy: DashboardTableOccupancy[];
    recentActivity: DashboardActivity[];
};
