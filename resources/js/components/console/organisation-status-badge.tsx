import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import type { ConsoleOrganisationStatus } from '@/types';

const variants: Record<
    ConsoleOrganisationStatus,
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    trial: 'outline',
    active: 'secondary',
    past_due: 'destructive',
    suspended: 'destructive',
    deletion_scheduled: 'outline',
    deleted_by_owner: 'destructive',
};

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
        <Badge variant={variants[status]}>
            {t(`console.statuses.${status}`)}
        </Badge>
    );
}
