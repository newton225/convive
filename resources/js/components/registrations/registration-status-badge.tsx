import { StatusBadge } from '@/components/status-badge';
import { registrationStatusTones } from '@/lib/status-tones';
import type { RegistrationShowStatus } from '@/types';

/**
 * Une couleur par etat du dossier (`registrationStatusTones`), pour le reperer d'un coup d'oeil dans
 * la base d'inscrits : la preuve rejetee, rouge, est le seul etat qui attend quelque chose de l'invite.
 */
export function RegistrationStatusBadge({
    status,
    label,
}: {
    status: RegistrationShowStatus;
    label: string;
}) {
    return (
        <StatusBadge
            tone={registrationStatusTones[status] ?? 'muted'}
            data-status={status}
        >
            {label}
        </StatusBadge>
    );
}
