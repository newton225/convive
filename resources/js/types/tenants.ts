export type Tenant = {
    id: number;
    name: string;
    slug: string;
    isPersonal: boolean;
    profileId?: number | null;
    profileName?: string | null;
    isOwner?: boolean;
    isCurrent?: boolean;
    planName?: string | null;
};

// Plan de l'organisation courante et son usage principal (menu lateral).
export type CurrentPlan = {
    name: string;
    activeEvents: number;
    maxActiveEvents: number | null;
    // Jours d'essai restants ; null hors essai, ou pour un essai sans date de fin.
    trialDaysLeft: number | null;
};

export type TenantMember = {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    profileId: number | null;
    profileName: string | null;
    isOwner: boolean;
};

export type TenantInvitation = {
    code: string;
    email: string;
    profileId: number;
    profileName: string;
    created_at: string;
};

export type TenantInvitationContext = {
    code: string;
    tenantName: string;
};

export type DashboardInvitation = {
    code: string;
    inviterName: string;
    tenant: {
        name: string;
        slug: string;
    };
};

/** Les permissions effectives du membre sur le locataire courant. */
export type TenantPermissions = {
    values: string[];
};

export type ProfileOption = {
    id: number;
    name: string;
    isSystem: boolean;
};

export type TenantProfile = {
    id: number;
    name: string;
    description: string | null;
    isSystem: boolean;
    requiresTwoFactor: boolean;
    permissions: string[];
    memberCount: number;
};

export type PermissionOption = {
    value: string;
    // Libelle complet (« Creer un evenement ») et libelle court dans son module (« Creer »).
    label: string;
    action: string;
    // Permission sans laquelle celle-ci est inutilisable (la consultation du module), ou null.
    requires: string | null;
};

// Le profil tel que l'editeur le recoit (creation : null).
export type ProfileEditorProfile = {
    id: number;
    name: string;
    description: string | null;
    requiresTwoFactor: boolean;
    permissions: string[];
    memberCount: number;
};

export type PermissionDomain = {
    value: string;
    label: string;
    permissions: PermissionOption[];
};

export type TenantOrganisation = {
    id: number;
    name: string;
    slug: string;
    subdomain: string | null;
    isPersonal: boolean;
    isReadyToPublish: boolean;
    missingBeforePublishing: string[];
};

export type TenantBranding = {
    displayName: string | null;
    legalName: string | null;
    legalForm: string | null;
    representativeName: string | null;
    registrationNumber: string | null;
    taxNumber: string | null;
    address: string | null;
    city: string | null;
    country: string | null;
    email: string | null;
    phone: string | null;
    colors: { primary: string; secondary: string };
    files: Record<string, string | null>;
};

export type LegalFormOption = {
    value: string;
    label: string;
};

export type BrandFileOption = {
    value: string;
    label: string;
    hint: string;
    // Proportions imposees (fonds du billet) : le depot passe alors par un rognage.
    crop?: { width: number; height: number } | null;
};

export type TenantUnit = {
    id: number;
    name: string;
    position: number;
    isActive: boolean;
    isNone: boolean;
};

export type PaymentChannelOption = {
    value: string;
    label: string;
    hasAccountNumber: boolean;
};

export type PaymentAccountPendingChange = {
    channelLabel: string | null;
    accountNumber: string | null;
    holderName: string | null;
    activatesAt: string | null;
    requestedBy: string | null;
    mayApprove: boolean;
};

export type PaymentAccount = {
    id: number;
    label: string;
    channel: string | null;
    channelLabel: string | null;
    accountNumber: string | null;
    holderName: string | null;
    instructions: string | null;
    isActive: boolean;
    isPubliclyVisible: boolean;
    changedRecently: boolean;
    pending: PaymentAccountPendingChange | null;
};
