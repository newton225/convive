import { router } from '@inertiajs/react';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';
import { dashboard } from '@/routes';
import type { DashboardEventChoice } from '@/types';

type Props = {
    tenantSlug: string;
    choices: DashboardEventChoice[];
    // L'evenement choisi a la main ; null : le choix automatique (le plus proche).
    selectedEventId: number | null;
};

const Automatic = 'auto';

/**
 * Choisir l'evenement que resume le tableau de bord. Par defaut, l'application retient l'evenement
 * ouvert ou en cours le plus proche ; le choix se garde dans l'adresse, qu'on peut donc partager
 * ou rouvrir.
 */
export function DashboardEventPicker({
    tenantSlug,
    choices,
    selectedEventId,
}: Props) {
    const { t, locale } = useTranslation();

    if (choices.length < 2) {
        return null;
    }

    const choose = (value: string) => {
        router.get(
            dashboard(tenantSlug).url,
            value === Automatic ? {} : { event: value },
            { preserveScroll: true },
        );
    };

    return (
        <div data-test="dashboard-event-picker">
            {/* Lu par les lecteurs d'ecran seulement : visible, il decalait le champ par rapport aux
                boutons voisins. */}
            <Label htmlFor="dashboard-event" className="sr-only">
                {t('dashboard.event_picker.label')}
            </Label>
            <Select
                value={
                    selectedEventId === null
                        ? Automatic
                        : String(selectedEventId)
                }
                onValueChange={choose}
            >
                <SelectTrigger
                    id="dashboard-event"
                    className="w-96 max-w-full"
                    aria-label={t('dashboard.event_picker.label')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value={Automatic}>
                        {t('dashboard.event_picker.automatic')}
                    </SelectItem>
                    {choices.map((choice) => (
                        <SelectItem key={choice.id} value={String(choice.id)}>
                            {choice.startsAt
                                ? `${choice.name} · ${formatDate(choice.startsAt, locale)}`
                                : choice.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
