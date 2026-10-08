export type TicketModel = 'classic' | 'sober' | 'elegant';

export type TicketBrand = {
    displayName: string;
    colors: { primary: string; secondary: string };
    logoUrl: string | null;
    stampUrl: string | null;
    signatureUrl: string | null;
    backgroundUrl: string | null;
    bodyBackgroundUrl: string | null;
    representative: string | null;
};

export type TicketTemplateEvent = {
    id: number;
    name: string;
    startsAt: string | null;
    venue: string | null;
};

export type TicketElements = {
    logo: boolean;
    stamp: boolean;
    signature: boolean;
    companions: boolean;
};

// Le billet talon d'une personne (`App\Support\TicketCard`), le meme pour l'invite principal et
// chacun de ses accompagnateurs, sur sa page, son lien individuel et dans le PDF.
export type TicketCardData = {
    holder: { name: string; unit: string };
    // Null seulement dans l'apercu du gabarit, qui montre un QR d'exemple.
    qrImage: string | null;
    tableNumber: number | null;
    // Faux pour un evenement sans table : le billet n'en parle pas.
    seatsAtTables: boolean;
    // Le tarif choisi par cette personne, null pour un billet d'avant les categories.
    priceCategory: string | null;
    // Tout le groupe sur le billet principal, 1 sur celui d'un accompagnateur.
    seats: number;
    // Accompagnateurs de l'invite principal, si le gabarit les affiche ; vide sinon.
    companions: { name: string; unit: string; priceCategory: string | null }[];
    // La personne qui invite un accompagnateur ; null sur le billet principal.
    host: { name: string; unit: string; reference: string | null } | null;
};

export type TicketCardEvent = {
    name: string;
    startsAt: string | null;
    venue: string | null;
};

export type TicketCardBrand = {
    displayName: string;
    colors: { primary: string; secondary: string };
    logoUrl: string | null;
    stampUrl: string | null;
    signatureUrl: string | null;
    // Image derriere le QR, deja recadree aux proportions du haut du talon ; null sans fond.
    backgroundUrl: string | null;
    // Image derriere le texte, sous la ligne perforee, recadree aux proportions du bas du talon et
    // affichee estompee ; null sans fond.
    bodyBackgroundUrl: string | null;
};

export type TicketDesign = {
    model: TicketModel;
    elements: TicketElements;
    brand: TicketCardBrand;
};
