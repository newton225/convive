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

type Props = {
    defaultCategories: EventPriceCategoryInput[];
    defaultPrice: number;
    errors: Record<string, string | undefined>;
};

export function PriceCategoriesField({
    defaultCategories,
    defaultPrice,
    errors,
}: Props) {
    const { t, locale } = useTranslation();
    const [categories, setCategories] = useState<EventPriceCategoryInput[]>(
        defaultCategories.length > 0
            ? defaultCategories
            : [
                  {
                      name: t('events.price_categories.default_name'),
                      price: defaultPrice,
                      quota: null,
                  },
              ],
    );

    const update = (
        index: number,
        values: Partial<EventPriceCategoryInput>,
    ) => {
        setCategories((current) =>
            current.map((category, categoryIndex) =>
                categoryIndex === index ? { ...category, ...values } : category,
            ),
        );
    };

    const minimumPrice = Math.min(...categories.map(({ price }) => price));

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
                                    price: Number(event.target.value),
                                })
                            }
                            required
                            data-test="price-category-price"
                        />
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
                            value={category.quota ?? ''}
                            onChange={(event) =>
                                update(index, {
                                    quota: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
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
                        { name: '', price: 0, quota: null },
                    ])
                }
            >
                <Plus /> {t('events.price_categories.add')}
            </Button>
            <p className="text-muted-foreground text-sm">
                {t('events.price_categories.minimum', {
                    price: formatAmount(minimumPrice, locale),
                })}
            </p>
        </div>
    );
}
