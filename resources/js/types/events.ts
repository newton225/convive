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

// Une carte de « Mes evenements » (README ecran 12).
export type EventListItem = EventSummary & {
    visualUrl: string | null;
    occupiedSeats: number;
    collectedAmount: number;
    proofsToCheck: number;
};

export type EventDetails = EventSummary & {
    venueAddress: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
    visualUrl: string | null;
    tableGroups: EventTableGroup[];
    companionLimit: number;
    registrationDeadline: string | null;
    purgeAt: string | null;
    invitationsSendAt: string | null;
    holdDurationMinutes: number;
    startsAtLocal: string | null;
    paymentAccountIds: number[];
};

export type EventTemplateOption = {
    id: number;
    name: string;
    tableCount: number;
    pricePerPerson: number;
};

// Ce qu'un evenement modele transmet au nouvel evenement : ni nom ni dates.
export type EventTemplate = {
    sourceId: number;
    sourceName: string;
    subtitle: string | null;
    venue: string | null;
    venueAddress: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
    tableGroups: EventTableGroup[];
    pricePerPerson: number;
    companionLimit: number;
    holdDurationMinutes: number;
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
    // Null quand l'organisateur masque le nombre de places (le defaut) : seul « Complet » se voit.
    showRemainingSeats: boolean;
    remainingSeats: number | null;
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

// Un groupe de tables de meme taille (« 3 tables de 12 ») : la salle se decrit ainsi, les tables
// n'ayant pas toutes le meme nombre de places (decision du 2026-09-29).
export type EventTableGroup = {
    count: number;
    seats: number;
};
