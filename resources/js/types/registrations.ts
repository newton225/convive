import type {
    TicketCardData,
    TicketCardEvent,
    TicketDesign,
} from './ticket-template';
import type { PublicPriceCategory } from './events';

export type PublicRegistrationEvent = {
    name: string;
    pricePerPerson: number;
    priceCategories: PublicPriceCategory[];
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

// Le billet d'un accompagnateur (README 2.8, un billet par personne), avec son lien individuel :
// null quand l'organisation n'a pas encore de sous-domaine pour le construire.
export type CompanionTicketPass = {
    id: number;
    name: string;
    unit: string;
    qrImage: string;
    shareUrl: string | null;
    card: TicketCardData;
};

export type TicketSummary = TicketDesign & {
    qrImage: string;
    card: TicketCardData;
    event: TicketCardEvent;
    passes: CompanionTicketPass[];
    tableNumber: number | null;
    // Tous les billets du groupe en un PDF (README 2.8), null sans sous-domaine ni lien public.
    pdfUrl: string | null;
    scheduledSendAt: string | null;
};

// Le lien individuel d'un billet (`Public\TicketController`).
export type PublicTicketPass = {
    name: string;
    unit: string;
    // La personne qui invite cet accompagnateur ; null sur le billet de l'invite principal.
    host: { name: string; unit: string; reference: string | null } | null;
    qrImage: string;
    tableNumber: number | null;
    pdfUrl: string | null;
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
    // Capture de la derniere preuve, quel que soit le statut : null sans preuve, sans capture, ou
    // sans la permission de voir les preuves.
    receiptUrl: string | null;
    // Reference de transaction et heure du depot de cette meme preuve, pour la fiche de l'apercu.
    proofReference: string | null;
    proofSubmittedAt: string | null;
    enteredCount: number;
    enteredAt: string | null;
    cardSentAt: string | null;
    // Liens de la carte et des billets (README 2.7) : null sans la permission d'envoi, ou pour
    // une inscription qui n'est pas validee.
    card: RegistrationCard | null;
};

export type RegistrationCardPerson = {
    // Null pour l'invite principal : sa carte, qui donne acces a tout le groupe.
    ticketId: number | null;
    name: string;
    isHolder: boolean;
    phone: string | null;
    url: string | null;
    message: string | null;
};

export type RegistrationCard = {
    people: RegistrationCardPerson[];
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
