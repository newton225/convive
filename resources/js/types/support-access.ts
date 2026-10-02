export type SupportAccessView = {
    id: number;
    at: string;
    page: string;
};

export type ActiveSupportAccess = {
    id: number;
    operator: string;
    grantedBy: string | null;
    // Pourquoi l'acces a ete ouvert ; null seulement sur un acces anterieur au motif obligatoire.
    reason: string | null;
    // L'evenement auquel l'acces est limite ; null : toute l'organisation.
    event: string | null;
    grantedAt: string;
    expiresAt: string;
    views: SupportAccessView[];
};

export type PastSupportAccess = {
    id: number;
    operator: string;
    reason: string | null;
    event: string | null;
    grantedAt: string;
    endedAt: string;
    endReason: 'expired' | 'revoked' | 'finished';
    // La note laissee par la personne de l'equipe Convive quand elle a ferme l'acces elle-meme.
    closingNote: string | null;
    viewsCount: number;
};

// La demande d'aide en attente, vue par l'organisation : envoyee quand personne de l'equipe
// Convive n'est visible. `takenBy` est le nom de la personne qui l'a prise en charge.
export type PendingSupportRequest = {
    id: number;
    reason: string;
    requestedAt: string;
    takenBy: string | null;
    takenById: number | null;
};

// Une demande d'aide en attente, listee dans la console.
export type ConsoleSupportRequest = {
    id: number;
    organisation: string;
    requestedBy: string | null;
    reason: string;
    requestedAt: string;
    takenBy: string | null;
    takenByMe: boolean;
};

// Un evenement de l'organisation, propose pour limiter l'acces.
export type SupportEventOption = {
    id: number;
    name: string;
};

export type SupportOperatorOption = {
    id: number;
    name: string;
};

// L'acces de support en cours sur l'organisation affichee (prop partagee) : le bandeau permanent.
// `viewing` est vrai pour la personne de l'equipe Convive qui consulte sous cet acces.
export type SupportAccessNotice = {
    id: number;
    operator: string;
    // L'evenement auquel l'acces est limite ; null : toute l'organisation.
    event: string | null;
    expiresAt: string;
    viewing: boolean;
};

// Un acces ouvert au compte connecte, liste dans la console : sa seule porte vers le contenu
// d'une organisation.
export type ConsoleSupportGrant = {
    id: number;
    organisation: string;
    reason: string | null;
    event: string | null;
    url: string;
    expiresAt: string;
};
