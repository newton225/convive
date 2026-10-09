import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { RegistrationPriceSummary, RegistrationShow } from '@/types';

type Props = {
    registration: Pick<
        RegistrationShow,
        | 'name'
        | 'phone'
        | 'email'
        | 'unit'
        | 'priceCategory'
        | 'amountDue'
        | 'companions'
    >;
};

/**
 * Le recapitulatif de la premiere page, relu avant de payer : qui est inscrit, avec quelle unite et
 * quel tarif, le telephone et l'email donnes, et le montant du. L'invite verifie d'un coup d'oeil
 * qu'il paie la bonne somme pour les bonnes personnes.
 */
export function RegistrationSummaryCard({ registration }: Props) {
    const { t, locale } = useTranslation();

    const people: Array<{
        key: string;
        name: string;
        unit: string;
        priceCategory: RegistrationPriceSummary | null;
        isGuest: boolean;
    }> = [
        {
            key: 'guest',
            name: registration.name,
            unit: registration.unit,
            priceCategory: registration.priceCategory,
            isGuest: true,
        },
        ...registration.companions.map((companion, index) => ({
            key: `companion-${index}`,
            name: companion.name,
            unit: companion.unit,
            priceCategory: companion.priceCategory,
            isGuest: false,
        })),
    ];

    const priceLabel = (price: number) =>
        price === 0
            ? t('guest.registration.summary.free')
            : formatAmount(price, locale);

    return (
        <Card data-test="registration-summary">
            <CardHeader>
                <CardTitle className="text-base">
                    {t('guest.registration.summary.title')}
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <ul className="divide-y text-sm">
                    {people.map((person) => (
                        <li
                            key={person.key}
                            className="flex items-start justify-between gap-3 py-2.5 first:pt-0"
                            data-test="registration-summary-person"
                        >
                            <span className="min-w-0">
                                <span className="block font-medium break-words">
                                    {person.name}
                                </span>
                                <span className="text-muted-foreground block text-xs">
                                    {person.isGuest
                                        ? t('guest.registration.summary.guest')
                                        : t(
                                              'guest.registration.summary.companion',
                                          )}
                                    {' · '}
                                    {person.unit}
                                    {person.priceCategory
                                        ? ` · ${person.priceCategory.name}`
                                        : ''}
                                </span>
                            </span>
                            {person.priceCategory ? (
                                <span className="shrink-0 font-medium tabular-nums">
                                    {priceLabel(person.priceCategory.price)}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>

                <dl className="text-muted-foreground space-y-1 border-t pt-3 text-xs">
                    <div className="flex justify-between gap-3">
                        <dt>{t('guest.registration.summary.phone')}</dt>
                        <dd className="text-foreground">
                            {registration.phone}
                        </dd>
                    </div>
                    {registration.email ? (
                        <div className="flex justify-between gap-3">
                            <dt>{t('guest.registration.summary.email')}</dt>
                            <dd className="text-foreground break-all">
                                {registration.email}
                            </dd>
                        </div>
                    ) : null}
                </dl>

                <div className="flex items-center justify-between border-t pt-3">
                    <span className="text-sm font-medium">
                        {t('guest.registration.total.label')}
                    </span>
                    <span
                        className="text-lg font-semibold"
                        data-test="registration-summary-total"
                    >
                        {formatAmount(registration.amountDue, locale)}
                    </span>
                </div>
            </CardContent>
        </Card>
    );
}
