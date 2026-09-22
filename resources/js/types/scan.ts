export type ScanResultValue = 'accepted' | 'already_scanned' | 'refused';

export type ScanRegistrationSummary = {
    name: string;
    unit: string;
    partySize: number;
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
};
