import type { TicketElements, TicketModel } from './ticket-template';

export type PublicRegistrationEvent = {
    name: string;
    pricePerPerson: number;
    companionLimit: number;
    remainingSeats: number;
};

export type PublicRegistrationTenant = {
    displayName: string;
    colors: { primary: string; secondary: string };
    logoUrl: string | null;
};

export type PublicUnitOption = {
    id: number;
    name: string;
};

export type RegistrationCompanionSummary = {
    name: string;
    unit: string;
};

export type RegistrationShowStatus =
    | 'draft'
    | 'held'
    | 'proof_submitted'
    | 'confirmed'
    | 'expired'
    | 'proof_rejected'
    | 'cancelled';

export type TicketSummaryBrand = {
    displayName: string | null;
    logoUrl: string | null;
    stampUrl: string | null;
    signatureUrl: string | null;
};

export type TicketSummary = {
    qrImage: string;
    tableNumber: number | null;
    scheduledSendAt: string | null;
    model: TicketModel;
    elements: TicketElements;
    brand: TicketSummaryBrand;
};

export type RegistrationShow = {
    name: string;
    unit: string;
    amountDue: number;
    status: RegistrationShowStatus;
    heldUntil: string | null;
    companions: RegistrationCompanionSummary[];
    ticket: TicketSummary | null;
};

export type RegistrationRow = {
    id: number;
    name: string;
    phone: string;
    unit: string;
    partySize: number;
    amountDue: number;
    status: RegistrationShowStatus;
    statusLabel: string;
    tableNumber: number | null;
    cancellationReason: string | null;
};

export type RegistrationsFilters = {
    search: string | null;
    status: string | null;
};

export type RegistrationsMeta = {
    currentPage: number;
    lastPage: number;
    total: number;
};

export type PublicPaymentAccount = {
    id: number;
    label: string;
    channelLabel: string | null;
    accountNumber: string | null;
    holderName: string | null;
    instructions: string | null;
};

export type PublicPaymentChannel = {
    value: string;
    label: string;
    hasAccountNumber: boolean;
};
