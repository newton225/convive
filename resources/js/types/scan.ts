export type ScanResultValue = 'accepted' | 'already_scanned' | 'refused';

export type ScanRegistrationSummary = {
    name: string;
    unit: string;
    partySize: number;
    // Nom de l'invite quand le billet est celui d'un accompagnateur (README 2.8).
    guestOf: string | null;
    tableNumber: number | null;
};

export type ScanOutcome = {
    result: ScanResultValue;
    forced: boolean;
    // Entree validee sans scan : l'invite a ete retrouve par sa reference ou son nom.
    manual?: boolean;
    registration: ScanRegistrationSummary | null;
    firstScannedAt: string | null;
    firstScannedBy: string | null;
    // Vrai billet d'un autre evenement de l'organisation le meme jour : ou l'invite est attendu.
    otherEvent?: ScanOtherEvent | null;
};

export type ScanOtherEvent = {
    name: string;
    venue: string | null;
    startsAt: string | null;
};

export type ScanSameDayEvent = ScanOtherEvent & {
    id: number;
};

export type ScanRecentRow = {
    id: number;
    result: ScanResultValue;
    forced: boolean;
    manual: boolean;
    name: string | null;
    scannedAt: string | null;
    station: string | null;
};

export type ScanLookupTicket = {
    id: number;
    name: string;
    unit: string;
    guestOf: string | null;
    reference: string | null;
    tableNumber: number | null;
    // Premier passage deja enregistre pour ce billet, null s'il n'a pas encore servi.
    arrivedAt: string | null;
    arrivedBy: string | null;
};

// Recherche d'un invite a l'entree quand son QR ne peut pas etre lu (README ecran 26).
export type ScanLookup = {
    search: string;
    tooShort: boolean;
    minimumLength: number;
    limit: number;
    truncated: boolean;
    tickets: ScanLookupTicket[];
};

export type ScanEventProps = {
    id: number;
    name: string;
    venue: string | null;
    startsAt: string | null;
    qrPublicKey: string | null;
    qrKeyVersion: number;
    closed: boolean;
    // Echeance des billets en secondes depuis l'epoque (SECURITY.md C2), null sans date.
    ticketValidUntil: number | null;
    // Heure d'ouverture des portes en secondes, null quand l'organisateur n'en fixe pas.
    ticketValidFrom: number | null;
};
