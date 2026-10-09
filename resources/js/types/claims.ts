export type ClaimCategoryValue = 'payment' | 'refund' | 'ticket' | 'other';

export type ClaimStatusValue = 'open' | 'resolved';

export type ClaimStatusFilter = ClaimStatusValue | 'all';

// Une ligne de la liste des reclamations d'un evenement (`Events\GuestClaimController::index`).
export type GuestClaimRow = {
    id: number;
    category: ClaimCategoryValue;
    message: string;
    status: ClaimStatusValue;
    createdAt: string | null;
    resolvedAt: string | null;
    name: string;
    reference: string | null;
    phone: string;
    registrationStatus: string;
};

// Ce que la page du dossier de l'invite recoit pour son formulaire de reclamation.
export type GuestClaimForm = {
    registrationId: number;
    // Signature du lien de retour du dossier : c'est elle qui prouve le dossier.
    signature: string;
    // Faux quand le dossier a deja atteint le nombre de reclamations ouvertes.
    canSubmit: boolean;
    categories: ClaimCategoryValue[];
};
