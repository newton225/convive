import type { TicketElements, TicketModel } from './ticket-template';

export type PublicRegistrationEvent = {
    name: string;
    pricePerPerson: number;
    companionLimit: number;
    remainingSeats: number | null;
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

// Le billet d'un accompagnateur (README 2.8, un billet par personne), avec son lien individuel :
// null quand l'organisation n'a pas encore de sous-domaine pour le construire.
export type CompanionTicketPass = {
    id: number;
    name: string;
    unit: string;
    qrImage: string;
    shareUrl: string | null;
};

export type TicketSummary = {
    qrImage: string;
    passes: CompanionTicketPass[];
    tableNumber: number | null;
    scheduledSendAt: string | null;
    model: TicketModel;
    elements: TicketElements;
    brand: TicketSummaryBrand;
};

// Le lien individuel d'un billet (`Public\TicketController`).
export type PublicTicketPass = {
    name: string;
    unit: string;
    guestOf: string | null;
    qrImage: string;
    tableNumber: number | null;
};

export type RegistrationShow = {
    // Reference de dossier lisible (« SP-2026-0008 »), affichage seulement.
    reference: string | null;
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
    reference: string | null;
    name: string;
    phone: string;
    unit: string;
    partySize: number;
    amountDue: number;
    status: RegistrationShowStatus;
    statusLabel: string;
    tableNumber: number | null;
    cancellationReason: string | null;
    // Canal de la derniere preuve deposee, et heure du passage a l'entree (null tant qu'absent).
    channelLabel: string | null;
    enteredCount: number;
    enteredAt: string | null;
};

// Sort du paiement d'une inscription annulee (README 2.11), `App\Enums\RefundStatus`.
export type RefundStatus = 'due' | 'refunded' | 'kept';

export type RefundChannelOption = {
    value: string;
    label: string;
};

export type RegistrationCancellation = {
    id: number;
    name: string;
    reference: string | null;
    phone: string;
    reason: string | null;
    cancelledAt: string | null;
    cancelledBy: string | null;
    // Nuls tant que l'inscription n'avait rien encaisse au moment de l'annulation.
    amountPaid: number | null;
    refundStatus: RefundStatus | null;
    refundStatusLabel: string | null;
    refundChannelLabel: string | null;
    refundedOn: string | null;
    refundFee: number | null;
    netRefund: number | null;
    refundReference: string | null;
    refundKeptReason: string | null;
};

export type RegistrationsFilters = {
    search: string | null;
    status: string | null;
    // Parametre `sort` de spatie/laravel-query-builder : `name`, `-amount_due`...
    sort: string | null;
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
    requiresReference: boolean;
    accountNumber: string | null;
    holderName: string | null;
    instructions: string | null;
};
