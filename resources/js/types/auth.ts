export type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};

// Une session ouverte du membre (SECURITY.md, « Deconnexion et sessions »). Jamais l'identifiant
// de session : c'est lui qui ouvre le compte.
export type ConnectedDevice = {
    browser: string | null;
    platform: string | null;
    mobile: boolean;
    ipAddress: string | null;
    lastActiveAt: string;
    isCurrent: boolean;
};
