export type TicketModel = 'classic' | 'sober' | 'elegant';

export type TicketBrand = {
    displayName: string;
    colors: { primary: string; secondary: string };
    logoUrl: string | null;
    stampUrl: string | null;
    signatureUrl: string | null;
    representative: string | null;
};

export type TicketTemplateEvent = {
    id: number;
    name: string;
    startsAt: string | null;
};

export type TicketElements = {
    logo: boolean;
    stamp: boolean;
    signature: boolean;
    companions: boolean;
};
