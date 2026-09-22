export type ReconciliationOutcome =
    | 'matched'
    | 'amount_mismatch'
    | 'approximate_name'
    | 'no_registration';

export type ReconciliationLineRow = {
    id: number;
    lineNumber: number;
    occurredOn: string;
    reference: string | null;
    issuer: string;
    amount: number;
    outcome: ReconciliationOutcome;
    outcomeLabel: string;
    matchedRegistration: { id: number; name: string } | null;
    resolved: boolean;
};

export type ReconciliationImportSummary = {
    id: number;
    filename: string;
    rowCount: number;
    importedAt: string | null;
};

export type ReconciliationStats = {
    matched: number;
    amountMismatch: number;
    approximateName: number;
    noRegistration: number;
};

export type ReconciliationRegistrationOption = {
    id: number;
    name: string;
    amountDue: number;
};
