// L'ecran Tresorerie (`App\Support\TreasuryOverview`) : l'argent de toute l'organisation.
export type TreasuryTotals = {
    collected: number;
    refunded: number;
    fees: number;
    due: number;
    net: number;
};

export type TreasuryEventRow = TreasuryTotals & {
    id: number;
    name: string;
    startsAt: string | null;
    registrationsUrl: string;
};

export type TreasuryAccountRow = {
    label: string;
    channelLabel: string | null;
    collected: number;
    refunded: number;
    due: number;
};

export type TreasuryRefundDue = {
    id: number;
    reference: string | null;
    name: string;
    eventName: string;
    amount: number;
    cancelledAt: string | null;
    registrationsUrl: string;
};

export type TreasuryFilters = {
    from: string | null;
    to: string | null;
};
