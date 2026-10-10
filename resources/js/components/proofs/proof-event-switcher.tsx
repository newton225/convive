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
import { index as proofsIndex } from '@/routes/tenants/events/proofs';
import type { ProofEventChoice } from '@/types';

type Props = {
    tenantSlug: string;
    currentEventId: number;
    choices: ProofEventChoice[];
};

/**
 * Passer de la file de preuves d'un evenement a celle d'un autre, avec le nombre de preuves qui
 * attendent dans chacun. Masque tant qu'un seul evenement est concerne.
 */
export function ProofEventSwitcher({
    tenantSlug,
    currentEventId,
    choices,
}: Props) {
    const { t } = useTranslation();

    if (choices.length < 2) {
        return null;
    }

    return (
        <div data-test="proofs-event-switcher">
            <Label htmlFor="proofs-event" className="sr-only">
                {t('proofs.event_switcher.label')}
            </Label>
            <Select
                value={String(currentEventId)}
                onValueChange={(value) =>
                    router.get(proofsIndex([tenantSlug, Number(value)]).url)
                }
            >
                <SelectTrigger
                    id="proofs-event"
                    className="w-80 max-w-full"
                    aria-label={t('proofs.event_switcher.label')}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {choices.map((choice) => (
                        <SelectItem key={choice.id} value={String(choice.id)}>
                            {t('proofs.event_switcher.option', {
                                name: choice.name,
                                count: choice.proofsToCheck,
                            })}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}
