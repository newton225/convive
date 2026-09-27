export type EventSettingsEvent = {
    id: number;
    name: string;
    tableCount: number;
    seatsPerTable: number;
    capacity: number;
    registrationDeadline: string | null;
    purgeAt: string | null;
    invitationsSendAt: string | null;
    holdDurationMinutes: number;
    // Visuel propre a l'evenement (URL signee), null tant qu'aucun n'est depose.
    visualUrl: string | null;
};

export type EventReminders = {
    d7: boolean;
    d2: boolean;
    d1: boolean;
    dayOf: boolean;
};

export type EventRules = {
    scheduledSend: boolean;
    autoSeating: boolean;
    allowWithoutProof: boolean;
    proofLegibility: boolean;
    purgeOnExhaustion: boolean;
    temporaryHold: boolean;
    phoneVerification: boolean;
};
