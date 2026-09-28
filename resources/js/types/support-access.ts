export type SupportAccessView = {
    at: string;
    page: string;
};

export type ActiveSupportAccess = {
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
