import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { UnitStats } from '@/types';

type Props = {
    units: UnitStats[];
    // L'unite dont la liste est filtree, si elle l'est.
    selectedId: string | null;
    onSelect: (unitId: string) => void;
};

/**
 * Combien d'inscrits confirmes par unite (decision du 2026-10-31) : tout l'evenement, pas le filtre actif.
 * Chaque unite ouvre la liste de ses inscrits.
 */
export function UnitSummary({ units, selectedId, onSelect }: Props) {
    const { t } = useTranslation();

    if (!units.some((unit) => unit.people > 0)) {
        return null;
    }

    return (
        <section className="space-y-2" data-test="unit-summary">
            <div>
                <h2 className="text-sm font-medium">
                    {t('registrations.unit_summary.title')}
                </h2>
                <p className="text-muted-foreground text-xs">
                    {t('registrations.unit_summary.hint')}
                </p>
            </div>
            <ul className="flex flex-wrap gap-2">
                {units.map((unit) => (
                    <li key={unit.id}>
                        <Button
                            type="button"
                            variant={
                                selectedId === String(unit.id)
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            className={cn(
                                'h-auto flex-col items-start gap-0 px-3 py-1.5',
                                unit.people === 0 && 'text-muted-foreground',
                            )}
                            onClick={() => onSelect(String(unit.id))}
                            data-test="unit-summary-item"
                        >
                            <span className="font-medium">{unit.name}</span>
                            <span className="text-xs font-normal">
                                {t('registrations.unit_summary.line', {
                                    people: unit.people,
                                    registrations: unit.registrations,
                                })}
                            </span>
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
