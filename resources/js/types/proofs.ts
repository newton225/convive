export type PaymentProofSignals = {
    duplicateReference: boolean;
    duplicateImage: boolean;
    referenceMissingFromStatement: boolean;
    statementAmountMismatch: boolean;
    // L'invite a laisse une precision : une information a lire, pas un soupcon.
    guestNote: boolean;
};

export type PaymentProofRow = {
    registrationId: number;
    proofId: number;
    registrationReference: string | null;
    name: string;
    phone: string;
    unit: string;
    partySize: number;
    companions: { name: string; unit: string }[];
    amountDue: number;
    submittedAt: string | null;
    channelLabel: string;
    reference: string | null;
    guestNote: string | null;
    paymentAccountLabel: string;
    receiptUrl: string | null;
    signals: PaymentProofSignals;
};
