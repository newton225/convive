export type NotificationAlert = {
    id: string;
    // Valeur de `App\Enums\NotificationType`, null pour un type retire depuis l'envoi.
    type: string | null;
    title: string;
    read: boolean;
    tenantName: string | null;
    createdAt: string | null;
};

export type NotificationsSummary = {
    unreadCount: number;
    latest: NotificationAlert[];
};

export type NotificationChannelValue = 'app' | 'mail' | 'both';

export type NotificationPreferenceRow = {
    type: string;
    label: string;
    channel: NotificationChannelValue;
};

export type NotificationChannelOption = {
    value: NotificationChannelValue;
    label: string;
};
