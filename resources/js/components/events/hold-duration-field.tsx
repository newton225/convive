import { useState } from 'react';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { Slider } from '@/components/ui/slider';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    defaultValue: number;
    min: number;
    max: number;
    error?: string;
};

/**
 * La duree de reservation, choisie au curseur entre les bornes reglees depuis la console (decision
 * du proprietaire du projet, 2026-10-07). Une valeur enregistree hors des bornes, d'avant leur
 * reglage, est ramenee dedans. Le serveur revalide les bornes.
 */
export function HoldDurationField({ defaultValue, min, max, error }: Props) {
    const { t } = useTranslation();
    const [minutes, setMinutes] = useState(
        Math.min(Math.max(defaultValue, min), max),
    );

    return (
        <div className="grid gap-2">
            <LabelWithHelp
                htmlFor="hold_duration_minutes"
                label={t('events.fields.hold_duration_minutes')}
                help={t('events.help.hold_duration_minutes')}
            />
            <div className="flex items-center gap-4">
                <Slider
                    id="hold_duration_minutes"
                    min={min}
                    max={max}
                    step={1}
                    value={[minutes]}
                    onValueChange={([value]) => setMinutes(value ?? minutes)}
                    aria-label={t('events.fields.hold_duration_minutes')}
                    data-test="event-hold_duration_minutes"
                    className="flex-1"
                />
                <span className="w-24 shrink-0 text-right text-sm font-medium tabular-nums">
                    {t('events.hold_duration.value', { count: minutes })}
                </span>
            </div>
            <p className="text-muted-foreground text-xs">
                {t('events.hold_duration.bounds', { min, max })}
            </p>
            {/* Le curseur n'est pas un champ de formulaire : la valeur part par ce champ cache. */}
            <input type="hidden" name="hold_duration_minutes" value={minutes} />
            <InputError message={error} />
        </div>
    );
}
