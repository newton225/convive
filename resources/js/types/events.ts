export type TenantSummary = {
    id: number;
    name: string;
    slug: string;
    subdomain: string | null;
    isReadyToPublish: boolean;
};

export type EventSummary = {
    id: number;
    name: string;
    subtitle: string | null;
    status: string;
    statusLabel: string;
    startsAt: string | null;
    venue: string | null;
    capacity: number;
    pricePerPerson: number;
    isPublished: boolean;
    isReadyToPublish: boolean;
    publicUrl: string | null;
    isAnnounced: boolean;
};

export type EventDetails = EventSummary & {
    venueAddress: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
    visualUrl: string | null;
    tableCount: number;
    seatsPerTable: number;
    companionLimit: number;
    registrationDeadline: string | null;
    purgeAt: string | null;
    invitationsSendAt: string | null;
    holdDurationMinutes: number;
    startsAtLocal: string | null;
    paymentAccountIds: number[];
};

export type EventPaymentAccountOption = {
    id: number;
    label: string;
    channelLabel: string | null;
    accountNumber: string | null;
};

export type PublicEvent = {
    name: string;
    subtitle: string | null;
    startsAt: string | null;
    venue: string | null;
    venueAddress: string | null;
    capacity: number;
    remainingSeats: number;
    isFull: boolean;
    pricePerPerson: number;
    companionLimit: number;
    registrationDeadline: string | null;
    registrationDeadlineHasPassed: boolean;
    acceptsRegistrations: boolean;
    colors: { primary: string; secondary: string };
    visualUrl: string | null;
};

export type PublicTenant = {
    name: string;
    displayName: string;
    colors: { primary: string; secondary: string };
    logoUrl: string | null;
    bannerUrl: string | null;
};
