import { CalendarDays, MapPin } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import type { RegistrationPriceSummary, RegistrationShow } from '@/types';

type Props = {
    registration: Pick<
        RegistrationShow,
        | 'name'
        | 'phone'
        | 'email'
        | 'unit'
        | 'priceCategory'
        | 'breakdown'
        | 'createdAt'
        | 'amountDue'
        | 'companions'
    >;
    event: {
        startsAt: string | null;
        venue: string | null;
        venueAddress: string | null;
    };
};

/**
 * Le recapitulatif de la premiere page, relu avant de payer. C'est la page ou l'invite revient tant
 * qu'il n'a pas fini : il doit y retrouver de quel evenement il s'agit, qui est inscrit et a quel
 * tarif, ce que chaque tarif represente dans la somme, les coordonnees donnees, et le montant du.
 */
export function RegistrationSummaryCard({ registration, event }: Props) {
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
            <CardContent className="space-y-5">
                {event.startsAt || event.venue ? (
                    <div
                        className="text-muted-foreground space-y-1 text-sm"
                        data-test="registration-summary-event"
                    >
                        {event.startsAt ? (
                            <p className="flex items-start gap-2">
                                <CalendarDays className="mt-0.5 size-4 shrink-0" />
                                {formatDateTime(event.startsAt, locale)}
                            </p>
                        ) : null}
                        {event.venue ? (
                            <p className="flex items-start gap-2">
                                <MapPin className="mt-0.5 size-4 shrink-0" />
                                <span>
                                    {event.venue}
                                    {event.venueAddress
                                        ? `, ${event.venueAddress}`
                                        : ''}
                                </span>
                            </p>
                        ) : null}
                    </div>
                ) : null}

                <section className="space-y-2">
                    <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                        {t('guest.registration.summary.people', {
                            count: people.length,
                        })}
                    </h3>
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
                                            ? t(
                                                  'guest.registration.summary.guest',
                                              )
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
                </section>

                {registration.breakdown.length > 0 ? (
                    <section className="space-y-2">
                        <h3 className="text-muted-foreground text-xs font-medium tracking-wide uppercase">
                            {t('guest.registration.summary.by_price')}
                        </h3>
                        <ul className="space-y-1.5 text-sm">
                            {registration.breakdown.map((line) => (
                                <li
                                    key={line.name}
                                    className="flex items-baseline justify-between gap-3"
                                    data-test="registration-summary-line"
                                >
                                    <span>
                                        {line.name}
                                        <span className="text-muted-foreground">
                                            {' · '}
                                            {t(
                                                'guest.registration.summary.quantity',
                                                {
                                                    count: line.count,
                                                    price: priceLabel(
                                                        line.price,
                                                    ),
                                                },
                                            )}
                                        </span>
                                    </span>
                                    <span className="shrink-0 tabular-nums">
                                        {priceLabel(line.subtotal)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <div className="flex items-center justify-between border-t pt-4">
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
                    {registration.createdAt ? (
                        <div className="flex justify-between gap-3">
                            <dt>
                                {t('guest.registration.summary.registered_on')}
                            </dt>
                            <dd className="text-foreground">
                                {formatDateTime(registration.createdAt, locale)}
                            </dd>
                        </div>
                    ) : null}
                </dl>
            </CardContent>
        </Card>
    );
}
