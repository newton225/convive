import { Minus, Plus } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';

export type EventPriceCategoryInput = {
    id?: number;
    name: string;
    price: number;
    quota: number | null;
};

// La saisie reste une chaine tant qu'elle est en cours : la convertir a chaque frappe
// transformait un champ vide en 0, qui restait affiche devant le chiffre suivant (« 0100000 »).
type Row = {
    id?: number;
    name: string;
    price: string;
    quota: string;
};

type Props = {
    defaultCategories: EventPriceCategoryInput[];
    defaultPrice: number;
    capacity: number;
    errors: Record<string, string | undefined>;
};

export function PriceCategoriesField({
    defaultCategories,
    defaultPrice,
    capacity,
    errors,
}: Props) {
    const { t, locale } = useTranslation();
    const [categories, setCategories] = useState<Row[]>(() =>
        (defaultCategories.length > 0
            ? defaultCategories
            : [
                  {
                      name: t('events.price_categories.default_name'),
                      price: defaultPrice,
                      quota: null,
                  },
              ]
        ).map((category) => ({
            id: category.id,
            name: category.name,
            price: String(category.price),
            quota: category.quota === null ? '' : String(category.quota),
        })),
    );

    const update = (index: number, values: Partial<Row>) => {
        setCategories((current) =>
            current.map((category, categoryIndex) =>
                categoryIndex === index ? { ...category, ...values } : category,
            ),
        );
    };

    const minimumPrice = Math.min(
        ...categories.map(({ price }) => Math.max(0, Number(price) || 0)),
    );

    // Seuls les tarifs plafonnes comptent : un tarif sans quota n'a pas de limite propre.
    const unlimited = categories.some(({ quota }) => quota === '');
    const quotaTotal = unlimited
        ? 0
        : categories.reduce((sum, { quota }) => sum + (Number(quota) || 0), 0);

    return (
        <div className="space-y-4" data-test="price-categories-field">
            <input
                type="hidden"
                name="price_per_person"
                value={Number.isFinite(minimumPrice) ? minimumPrice : 0}
                readOnly
            />
            {categories.map((category, index) => (
                <div
                    key={category.id ?? `new-${index}`}
                    className="grid items-start gap-3 rounded-lg border p-4 sm:grid-cols-[minmax(0,1fr)_minmax(8rem,0.7fr)_minmax(8rem,0.7fr)_auto]"
                    data-test="price-category-row"
                >
                    {category.id ? (
                        <input
                            type="hidden"
                            name={`price_categories[${index}][id]`}
                            value={category.id}
                        />
                    ) : null}
                    <div className="space-y-2">
                        <Label
                            htmlFor={`price-category-name-${index}`}
                            required
                        >
                            {t('events.fields.price_category_name')}
                        </Label>
                        <Input
                            id={`price-category-name-${index}`}
                            name={`price_categories[${index}][name]`}
                            value={category.name}
                            onChange={(event) =>
                                update(index, { name: event.target.value })
                            }
                            maxLength={60}
                            required
                            data-test="price-category-name"
                        />
                        <InputError
                            message={errors[`price_categories.${index}.name`]}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label
                            htmlFor={`price-category-price-${index}`}
                            required
                        >
                            {t('events.fields.price_category_price')}
                        </Label>
                        <Input
                            id={`price-category-price-${index}`}
                            name={`price_categories[${index}][price]`}
                            type="number"
                            min={0}
                            max={100000000}
                            value={category.price}
                            onChange={(event) =>
                                update(index, {
                                    price: event.target.value.replace(
                                        /^0+(?=\d)/,
                                        '',
                                    ),
                                })
                            }
                            required
                            data-test="price-category-price"
                        />
                        {Number(category.price) === 0 ? (
                            <p className="text-muted-foreground text-xs">
                                {t('events.price_categories.free_hint')}
                            </p>
                        ) : null}
                        <InputError
                            message={errors[`price_categories.${index}.price`]}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`price-category-quota-${index}`}>
                            {t('events.fields.price_category_quota')}
                        </Label>
                        <Input
                            id={`price-category-quota-${index}`}
                            name={`price_categories[${index}][quota]`}
                            type="number"
                            min={1}
                            max={capacity > 0 ? capacity : undefined}
                            value={category.quota}
                            onChange={(event) =>
                                update(index, { quota: event.target.value })
                            }
                            data-test="price-category-quota"
                        />
                        <InputError
                            message={errors[`price_categories.${index}.quota`]}
                        />
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="mt-6 size-11"
                        aria-label={t('events.price_categories.remove', {
                            name: category.name,
                        })}
                        disabled={categories.length <= 1}
                        data-test="price-category-remove"
                        onClick={() =>
                            setCategories((current) =>
                                current.filter(
                                    (_, categoryIndex) =>
                                        categoryIndex !== index,
                                ),
                            )
                        }
                    >
                        <Minus />
                    </Button>
                </div>
            ))}
            <InputError message={errors.price_categories} />
            <Button
                type="button"
                variant="secondary"
                data-test="price-category-add"
                onClick={() =>
                    setCategories((current) => [
                        ...current,
                        { name: '', price: '0', quota: '' },
                    ])
                }
            >
                <Plus /> {t('events.price_categories.add')}
            </Button>
            {capacity > 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('events.price_categories.capacity_hint', {
                        count: capacity,
                    })}
                </p>
            ) : null}
            {capacity > 0 && quotaTotal > capacity ? (
                <p className="text-sm text-amber-600" role="status">
                    {t('events.price_categories.quotas_exceed', {
                        total: quotaTotal,
                        capacity,
                    })}
                </p>
            ) : null}
            <p className="text-muted-foreground text-sm">
                {t('events.price_categories.minimum', {
                    price:
                        minimumPrice === 0
                            ? t('events.price_categories.free')
                            : formatAmount(minimumPrice, locale),
                })}
            </p>
        </div>
    );
}
