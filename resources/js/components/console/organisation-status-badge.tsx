import { StatusBadge } from '@/components/status-badge';
import { useTranslation } from '@/hooks/use-translation';
import { organisationStatusTones } from '@/lib/status-tones';
import type { ConsoleOrganisationStatus } from '@/types';

/**
 * L'etat d'une organisation cliente, toujours ecrit en toutes lettres : la couleur ne porte
 * jamais seule l'information.
 */
export function OrganisationStatusBadge({
    status,
}: {
    status: ConsoleOrganisationStatus;
}) {
    const { t } = useTranslation();

    return (
        <StatusBadge tone={organisationStatusTones[status] ?? 'muted'}>
            {t(`console.statuses.${status}`)}
        </StatusBadge>
    );
}
