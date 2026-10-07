import { motion, useReducedMotion } from 'framer-motion';
import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef } from 'react';
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
import { Duration, EaseOut } from '@/lib/motion';
import { formatAmount } from '@/lib/format-currency';
import type { PublicPriceCategory, PublicUnitOption } from '@/types';

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
    required = false,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    help?: string;
    required?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div className="space-y-2">
            <LabelWithHelp
                htmlFor={id}
                label={label}
                help={help}
                required={required}
            />
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

export function PriceCategorySelect({
    id,
    name,
    categories,
    value,
    onValueChange,
    error,
    testId,
}: {
    id: string;
    name: string;
    categories: PublicPriceCategory[];
    value: number | undefined;
    onValueChange: (categoryId: number) => void;
    error?: string;
    testId: string;
}) {
    const { t, locale } = useTranslation();

    return (
        <Field
            id={id}
            required
            label={t('guest.registration.fields.price_category')}
            error={error}
        >
            <Select
                name={name}
                value={value === undefined ? undefined : String(value)}
                onValueChange={(categoryId) =>
                    onValueChange(Number(categoryId))
                }
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    data-test={testId}
                    aria-invalid={Boolean(error)}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {categories.map((category) => (
                        <SelectItem
                            key={category.id}
                            value={String(category.id)}
                            disabled={category.remainingQuota === 0}
                        >
                            {category.name} ·{' '}
                            {category.price === 0
                                ? t('guest.event.free')
                                : formatAmount(category.price, locale)}
                            {category.remainingQuota === 0
                                ? ` · ${t('guest.event.category_sold_out')}`
                                : ''}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

export function CompanionFields({
    units,
    priceCategories,
    priceCategoryIds,
    onPriceCategoryChange,
    companionLimit,
    companionIds,
    onAdd,
    onRemove,
    errors,
}: {
    units: PublicUnitOption[];
    priceCategories?: PublicPriceCategory[];
    priceCategoryIds?: Record<number, number | undefined>;
    onPriceCategoryChange?: (id: number, categoryId: number) => void;
    companionLimit: number;
    companionIds: number[];
    onAdd: () => void;
    onRemove: (id: number) => void;
    errors: Record<string, string | undefined>;
}) {
    const { t } = useTranslation();
    const atLimit = companionIds.length >= companionLimit;
    const reduceMotion = useReducedMotion() === true;
    // Seul un bloc ajoute d'un clic s'anime : ceux presents au chargement (retour apres une
    // erreur de validation) apparaissent tels quels. Aucune animation au retrait : un bloc qui
    // s'efface resterait un instant dans le formulaire avec son ancien indice de champ.
    const mounted = useRef(false);

    useEffect(() => {
        mounted.current = true;
    }, []);

    const animateEntry = mounted.current && !reduceMotion;

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-2">
                <CardTitle className="flex items-center gap-1.5 text-base">
                    {t('guest.registration.companions.title')}
                    <HelpTip subject={t('guest.registration.companions.title')}>
                        {t('guest.registration.help.companions')}
                    </HelpTip>
                </CardTitle>
                {companionLimit > 0 ? (
                    <p
                        className="text-muted-foreground text-sm tabular-nums"
                        aria-live="polite"
                        data-test="companion-count"
                    >
                        {t('guest.registration.companions.count', {
                            count: companionIds.length,
                            max: companionLimit,
                        })}
                    </p>
                ) : null}
            </CardHeader>
            <CardContent className="space-y-4">
                {companionIds.map((id, index) => (
                    <motion.div
                        key={id}
                        layout={reduceMotion ? false : 'position'}
                        initial={
                            animateEntry
                                ? { opacity: 0, y: -12, scale: 0.98 }
                                : false
                        }
                        animate={{ opacity: 1, y: 0, scale: 1 }}
                        transition={{ duration: Duration.quick, ease: EaseOut }}
                        className="space-y-2 rounded-lg border p-3"
                        data-test="companion-row"
                    >
                        <p className="text-sm font-medium">
                            {t('guest.registration.companions.item', {
                                number: index + 1,
                            })}
                        </p>
                        <div className="flex items-start gap-2">
                            <div className="flex-1 space-y-2">
                                <Label
                                    htmlFor={`companion-name-${id}`}
                                    required
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
                                    'guest.registration.companions.remove_item',
                                    { number: index + 1 },
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
                        {priceCategories &&
                        priceCategoryIds &&
                        onPriceCategoryChange ? (
                            <PriceCategorySelect
                                id={`companion-price-category-${id}`}
                                name={`companions[${index}][price_category_id]`}
                                categories={priceCategories}
                                value={priceCategoryIds[id]}
                                onValueChange={(categoryId) =>
                                    onPriceCategoryChange(id, categoryId)
                                }
                                error={
                                    errors[
                                        `companions.${index}.price_category_id`
                                    ]
                                }
                                testId="companion-price-category"
                            />
                        ) : null}
                    </motion.div>
                ))}

                <motion.div
                    layout={reduceMotion ? false : 'position'}
                    transition={{ duration: Duration.quick, ease: EaseOut }}
                >
                    <Button
                        type="button"
                        variant="secondary"
                        disabled={atLimit}
                        data-test="companion-add"
                        onClick={onAdd}
                    >
                        <Plus /> {t('guest.registration.companions.add')}
                    </Button>
                </motion.div>

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
