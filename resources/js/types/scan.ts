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
    registration: ScanRegistrationSummary | null;
    firstScannedAt: string | null;
    firstScannedBy: string | null;
};

export type ScanRecentRow = {
    id: number;
    result: ScanResultValue;
    forced: boolean;
    name: string | null;
    scannedAt: string | null;
    station: string | null;
};

export type ScanEventProps = {
    id: number;
    name: string;
    qrPublicKey: string | null;
    qrKeyVersion: number;
    closed: boolean;
    // Echeance des billets en secondes depuis l'epoque (SECURITY.md C2), null sans date.
    ticketValidUntil: number | null;
};
