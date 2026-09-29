import { Minus, Plus } from 'lucide-react';
import { HelpTip } from '@/components/help-tip';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { PublicUnitOption } from '@/types';

/**
 * Champs partages entre le formulaire d'inscription (README ecran 4) et celui de la liste
 * d'attente (ecran 10) : les deux collectent exactement les memes informations, un
 * accompagnateur y occupant une place comme le participant lui-meme (README 2.5).
 */
export function Field({
    id,
    label,
    error,
    help,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    help?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <LabelWithHelp htmlFor={id} label={label} help={help} />
            {children}
            <InputError message={error} />
        </div>
    );
}

export function UnitSelect({
    name,
    units,
    testId,
}: {
    name: string;
    units: PublicUnitOption[];
    testId: string;
}) {
    const { t } = useTranslation();

    return (
        <Select name={name}>
            <SelectTrigger className="w-full" data-test={testId}>
                <SelectValue
                    placeholder={t(
                        'guest.registration.fields.unit_placeholder',
                    )}
                />
            </SelectTrigger>
            <SelectContent>
                {units.map((unit) => (
                    <SelectItem key={unit.id} value={String(unit.id)}>
                        {unit.name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function CompanionFields({
    units,
    companionLimit,
    companionIds,
    onAdd,
    onRemove,
    errors,
}: {
    units: PublicUnitOption[];
    companionLimit: number;
    companionIds: number[];
    onAdd: () => void;
    onRemove: (id: number) => void;
    errors: Record<string, string | undefined>;
}) {
    const { t } = useTranslation();
    const atLimit = companionIds.length >= companionLimit;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-1.5 text-base">
                    {t('guest.registration.companions.title')}
                    <HelpTip subject={t('guest.registration.companions.title')}>
                        {t('guest.registration.help.companions')}
                    </HelpTip>
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {companionIds.map((id, index) => (
                    <div
                        key={id}
                        className="space-y-2 rounded-lg border p-3"
                        data-test="companion-row"
                    >
                        <div className="flex items-start gap-2">
                            <div className="flex-1 space-y-2">
                                <Label
                                    htmlFor={`companion-name-${id}`}
                                    className="sr-only"
                                >
                                    {t(
                                        'guest.registration.fields.companion_name',
                                    )}
                                </Label>
                                <Input
                                    id={`companion-name-${id}`}
                                    name={`companions[${index}][name]`}
                                    placeholder={t(
                                        'guest.registration.fields.companion_name',
                                    )}
                                    required
                                    data-test="companion-name"
                                />
                                <InputError
                                    message={errors[`companions.${index}.name`]}
                                />
                            </div>

                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label={t(
                                    'guest.registration.companions.remove',
                                )}
                                data-test="companion-remove"
                                onClick={() => onRemove(id)}
                            >
                                <Minus className="h-4 w-4" />
                            </Button>
                        </div>

                        <UnitSelect
                            name={`companions[${index}][unit_id]`}
                            units={units}
                            testId="companion-unit"
                        />
                        <InputError
                            message={errors[`companions.${index}.unit_id`]}
                        />
                    </div>
                ))}

                <Button
                    type="button"
                    variant="secondary"
                    disabled={atLimit}
                    data-test="companion-add"
                    onClick={onAdd}
                >
                    <Plus /> {t('guest.registration.companions.add')}
                </Button>

                {atLimit ? (
                    <p className="text-muted-foreground text-sm">
                        {t('guest.registration.companions.limit_reached', {
                            count: companionLimit,
                        })}
                    </p>
                ) : null}
            </CardContent>
        </Card>
    );
}
