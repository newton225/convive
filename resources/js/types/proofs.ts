export type PaymentProofSignals = {
    duplicateReference: boolean;
    duplicateImage: boolean;
    referenceMissingFromStatement: boolean;
    statementAmountMismatch: boolean;
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
    amountDeclared: number;
    paymentAccountLabel: string;
    receiptUrl: string | null;
    signals: PaymentProofSignals;
};
