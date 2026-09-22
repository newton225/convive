export type WaitlistEntryStatus =
    | 'waiting'
    | 'invited'
    | 'expired'
    | 'converted';

export type WaitlistEntryShow = {
    name: string;
    status: WaitlistEntryStatus;
    position: number;
    expiresAt: string | null;
};
