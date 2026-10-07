import { Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import { HelpTip } from '@/components/help-tip';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import type { EventTableGroup } from '@/types';

// La saisie reste une chaine tant qu'elle est en cours : la convertir a chaque frappe
// transformait un champ vide en 0, qui restait affiche devant le chiffre suivant (« 09 »).
type Row = { key: number; count: string; seats: string };

type Props = {
    defaultGroups: EventTableGroup[];
    errors: Record<string, string | undefined>;
};

/**
 * La salle decrite en groupes de tables de meme taille (README ecran 13, decision du 2026-09-29) :
 * « 3 tables de 12 places », « 20 tables de 8 places ». Le total se recalcule a chaque saisie ;
 * c'est le serveur qui cree les tables et refuse ce qui laisserait un invite place sans chaise.
 */
export function TableGroupsField({ defaultGroups, errors }: Props) {
    const { t } = useTranslation();
    const [nextKey, setNextKey] = useState(defaultGroups.length + 1);
    // Un nouvel evenement part sans table : aucune capacite inventee a la place de l'organisateur.
    const [rows, setRows] = useState<Row[]>(() =>
        defaultGroups.map((group, index) => ({
            key: index,
            count: String(group.count),
            seats: String(group.seats),
        })),
    );

    const update = (key: number, field: 'count' | 'seats', value: string) =>
        setRows((current) =>
            current.map((row) =>
                row.key === key ? { ...row, [field]: value } : row,
            ),
        );

    const add = () => {
        setRows((current) => [
            ...current,
            { key: nextKey, count: '1', seats: '4' },
        ]);
        setNextKey((key) => key + 1);
    };

    const remove = (key: number) =>
        setRows((current) => current.filter((row) => row.key !== key));

    const toCount = (value: string) => Math.max(0, Number(value) || 0);
    const tables = rows.reduce((total, row) => total + toCount(row.count), 0);
    const seats = rows.reduce(
        (total, row) => total + toCount(row.count) * toCount(row.seats),
        0,
    );

    return (
        <fieldset className="space-y-3" data-test="event-table-groups">
            <legend className="flex items-center gap-1.5 text-sm font-medium">
                {t('events.fields.table_groups')}
                <HelpTip subject={t('events.fields.table_groups')}>
                    {t('events.help.table_groups')}
                </HelpTip>
            </legend>

            {rows.length === 0 ? (
                <>
                    {/* Sans ligne, rien ne partirait : le serveur laisserait le plan tel quel au
                        lieu de le vider. Le champ vide dit explicitement « aucune table ». */}
                    <input type="hidden" name="table_groups" value="" />
                    <p className="text-muted-foreground text-sm">
                        {t('events.table_groups.empty')}
                    </p>
                </>
            ) : null}

            {rows.map((row, index) => (
                <div key={row.key} className="space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            type="number"
                            min={1}
                            required
                            name={`table_groups[${index}][count]`}
                            value={row.count}
                            onChange={(event) =>
                                update(row.key, 'count', event.target.value)
                            }
                            aria-label={t('events.fields.table_count')}
                            className="w-24"
                            data-test="event-table-group-count"
                        />
                        <span className="text-muted-foreground text-sm">
                            {t('events.table_groups.tables')}
                        </span>
                        <Input
                            type="number"
                            min={1}
                            required
                            name={`table_groups[${index}][seats]`}
                            value={row.seats}
                            onChange={(event) =>
                                update(row.key, 'seats', event.target.value)
                            }
                            aria-label={t('events.fields.seats_per_table')}
                            className="w-24"
                            data-test="event-table-group-seats"
                        />
                        <span className="text-muted-foreground text-sm">
                            {t('events.table_groups.seats')}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11"
                            onClick={() => remove(row.key)}
                            aria-label={t('events.table_groups.remove')}
                        >
                            <Minus />
                        </Button>
                    </div>
                    <InputError
                        message={
                            errors[`table_groups.${index}.count`] ??
                            errors[`table_groups.${index}.seats`]
                        }
                    />
                </div>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button type="button" variant="outline" size="sm" onClick={add}>
                    <Plus />
                    {t('events.table_groups.add')}
                </Button>
                <p className="text-sm font-medium" aria-live="polite">
                    {t('events.table_groups.total', {
                        count: seats,
                        tables: String(tables),
                    })}
                </p>
            </div>

            <InputError message={errors.table_groups} />
        </fieldset>
    );
}
