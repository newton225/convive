import type { RegistrationShowStatus } from './registrations';

export type PaymentProofSignals = {
    duplicateReference: boolean;
    duplicateImage: boolean;
    referenceMissingFromStatement: boolean;
    statementAmountMismatch: boolean;
    // L'invite a laisse une precision : une information a lire, pas un soupcon.
    guestNote: boolean;
};

// Une autre preuve portant la meme capture, tous evenements confondus : de quoi la comparer a
// celle qu'on examine. `receiptUrl` est null quand la capture n'est plus stockee.
export type DuplicateImageMatch = {
    proofId: number;
    registrationReference: string | null;
    name: string;
    eventName: string;
    sameEvent: boolean;
    status: RegistrationShowStatus;
    statusLabel: string;
    submittedAt: string | null;
    reference: string | null;
    receiptUrl: string | null;
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
    duplicateImageMatches: DuplicateImageMatch[];
    signals: PaymentProofSignals;
};
