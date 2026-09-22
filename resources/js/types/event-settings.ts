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
};
