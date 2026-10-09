export type TenantSummary = {
    id: number;
    name: string;
    slug: string;
    subdomain: string | null;
    // Le nom que voient les invites (`tenant_brandings.display_name`, sinon le nom de l'organisation).
    displayName: string;
    isReadyToPublish: boolean;
    // Faux tant que l'organisation n'a rien publie : la premiere publication demarre le delai
    // de 24 heures des comptes de versement.
    paymentDelayActive: boolean;
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
    // Faux pour un evenement sans table : personne n'y est assis a une table (plan de salle absent).
    seatsAtTables: boolean;
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
    // Lien vers un service de cartes, verifie par le serveur (`MapLink`).
    venueMapUrl: string | null;
    // Ce qui manque encore pour publier, puis pour annoncer sur la vitrine (cles du serveur).
    missingBeforePublishing: string[];
    missingBeforeAnnouncing: string[];
    primaryColor: string | null;
    secondaryColor: string | null;
    visualUrl: string | null;
    // Le nombre de places d'un evenement sans table, null quand la salle est en tables.
    freeSeats: number | null;
    // Vrai des qu'une inscription occupe une place : le mode avec ou sans tables ne change plus.
    seatingModeLocked: boolean;
    // Les places deja prises ou reservees : la capacite ne peut pas descendre dessous.
    occupiedSeats: number;
    tableGroups: EventTableGroup[];
    priceCategories: EventPriceCategory[];
    companionLimit: number;
    registrationDeadline: string | null;
    purgeAt: string | null;
    invitationsSendAt: string | null;
    holdDurationMinutes: number;
    startsAtLocal: string | null;
    endsAtLocal: string | null;
    // Fenetre d'entree : minutes d'ouverture avant le debut (null : sans limite), marge apres la fin.
    entryOpensMinutesBefore: number | null;
    entryGraceMinutes: number;
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
    // Lien vers un service de cartes, verifie par le serveur (`MapLink`).
    venueMapUrl: string | null;
    primaryColor: string | null;
    secondaryColor: string | null;
    seatsAtTables: boolean;
    freeSeats: number | null;
    tableGroups: EventTableGroup[];
    priceCategories: Omit<EventPriceCategory, 'id'>[];
    pricePerPerson: number;
    companionLimit: number;
    holdDurationMinutes: number;
    paymentAccountIds: number[];
};

export type EventPriceCategory = {
    id: number;
    name: string;
    price: number;
    quota: number | null;
    // Vrai quand quelqu'un l'a deja choisi : son nom et son prix sont figes.
    locked?: boolean;
};

export type PublicPriceCategory = EventPriceCategory & {
    remainingQuota: number | null;
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
    endsAt: string | null;
    venue: string | null;
    venueAddress: string | null;
    // Lien vers un service de cartes, verifie par le serveur (`MapLink`).
    venueMapUrl: string | null;
    capacity: number;
    // Null quand l'organisateur masque le nombre de places (le defaut) : seul « Complet » se voit.
    showRemainingSeats: boolean;
    remainingSeats: number | null;
    isFull: boolean;
    pricePerPerson: number;
    priceCategories: PublicPriceCategory[];
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
