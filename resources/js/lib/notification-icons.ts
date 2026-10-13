import {
    Armchair,
    Bell,
    CalendarCheck,
    CalendarPlus,
    CalendarX,
    Flag,
    ListPlus,
    UserCheck,
    UserMinus,
    Clock,
    FileDown,
    FileCheck,
    FileX,
    Gauge,
    Hourglass,
    MessageCircleWarning,
    MessageSquareWarning,
    ScanSearch,
    ShieldAlert,
    Trash2,
    UserPlus,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * L'icone de chaque type d'alerte (`App\Enums\NotificationType`), pour reconnaitre une alerte
 * avant de la lire. Un type inconnu retombe sur la cloche.
 */
const icons: Record<string, LucideIcon> = {
    proof_received: FileCheck,
    claim_received: MessageCircleWarning,
    proof_rejected: FileX,
    holds_expired: Clock,
    seats_exhausted: Users,
    seats_low: Armchair,
    purge_scheduled: Clock,
    registrations_purged: Trash2,
    team_invitation_pending: UserPlus,
    ticket_refused: ShieldAlert,
    entry_without_scan: ScanSearch,
    large_export: FileDown,
    message_quota_reached: MessageSquareWarning,
    plan_limits_lowered: Gauge,
    trial_ending: Hourglass,
    trial_ended: Hourglass,
    event_created: CalendarPlus,
    event_published: CalendarCheck,
    event_closed: CalendarX,
    event_ended: Flag,
    registrations_closed: Clock,
    registration_received: UserPlus,
    registration_confirmed: UserCheck,
    registration_cancelled: UserMinus,
    waitlist_joined: ListPlus,
};

export function notificationIcon(type: string | null): LucideIcon {
    return (type !== null && icons[type]) || Bell;
}
