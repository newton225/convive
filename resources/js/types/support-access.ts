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
    grantedAt: string;
    expiresAt: string;
    views: SupportAccessView[];
};

export type PastSupportAccess = {
    id: number;
    operator: string;
    reason: string | null;
    grantedAt: string;
    endedAt: string;
    endReason: 'expired' | 'revoked' | 'finished';
    // La note laissee par la personne de l'equipe Convive quand elle a ferme l'acces elle-meme.
    closingNote: string | null;
    viewsCount: number;
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
    expiresAt: string;
    viewing: boolean;
};

// Un acces ouvert au compte connecte, liste dans la console : sa seule porte vers le contenu
// d'une organisation.
export type ConsoleSupportGrant = {
    id: number;
    organisation: string;
    reason: string | null;
    url: string;
    expiresAt: string;
};
