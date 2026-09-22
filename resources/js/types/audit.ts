export type AuditEntry = {
    id: number;
    type: string;
    actor: string;
    subject: string | null;
    ip: string | null;
    at: string | null;
};

export type AuditMeta = {
    currentPage: number;
    lastPage: number;
    total: number;
};

export type AuditFilters = {
    search: string | null;
    type: string | null;
};
