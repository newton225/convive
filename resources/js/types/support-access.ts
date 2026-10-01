export type SupportAccessView = {
    id: number;
    at: string;
    page: string;
};

export type ActiveSupportAccess = {
    id: number;
    operator: string;
    grantedBy: string | null;
    grantedAt: string;
    expiresAt: string;
    views: SupportAccessView[];
};

export type PastSupportAccess = {
    id: number;
    operator: string;
    grantedAt: string;
    endedAt: string;
    endReason: 'expired' | 'revoked';
    viewsCount: number;
};

export type SupportOperatorOption = {
    id: number;
    name: string;
};

// L'acces de support en cours sur l'organisation affichee (prop partagee) : le bandeau permanent.
// `viewing` est vrai pour la personne de l'equipe Convive qui consulte sous cet acces.
export type SupportAccessNotice = {
    operator: string;
    expiresAt: string;
    viewing: boolean;
};

// Un acces ouvert au compte connecte, liste dans la console : sa seule porte vers le contenu
// d'une organisation.
export type ConsoleSupportGrant = {
    id: number;
    organisation: string;
    url: string;
    expiresAt: string;
};
