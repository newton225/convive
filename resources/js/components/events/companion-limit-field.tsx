import { useState } from 'react';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { Slider } from '@/components/ui/slider';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    defaultValue: number;
    max: number;
    error?: string;
};

/**
 * Le plafond d'accompagnateurs, choisi au curseur entre 0 et le maximum du produit. Le serveur
 * revalide la borne.
 */
export function CompanionLimitField({ defaultValue, max, error }: Props) {
    const { t } = useTranslation();
    const [limit, setLimit] = useState(
        Math.min(Math.max(defaultValue, 0), max),
    );

    return (
        <div className="grid gap-2">
            <LabelWithHelp
                htmlFor="companion_limit"
                label={t('events.fields.companion_limit')}
                help={t('events.help.companion_limit')}
            />
            <div className="flex items-center gap-4">
                <Slider
                    id="companion_limit"
                    min={0}
                    max={max}
                    step={1}
                    value={[limit]}
                    onValueChange={([value]) => setLimit(value ?? limit)}
                    aria-label={t('events.fields.companion_limit')}
                    data-test="event-companion_limit"
                    className="flex-1"
                />
                <span className="min-w-36 shrink-0 text-right text-sm font-medium whitespace-nowrap tabular-nums">
                    {t('events.companion_limit.value', { count: limit })}
                </span>
            </div>
            {/* Le curseur n'est pas un champ de formulaire : la valeur part par ce champ cache. */}
            <input type="hidden" name="companion_limit" value={limit} />
            <InputError message={error} />
        </div>
    );
}
