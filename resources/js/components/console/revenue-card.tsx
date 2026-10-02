import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format-currency';
import type { ConsoleAmountDue, ConsoleRevenue } from '@/types';

type Props = {
    revenue: ConsoleRevenue;
};

/**
 * Les revenus de l'editeur (README section 3) : ce que les abonnements a jour rapportent chaque
 * mois, et ce qui a reellement ete encaisse ce mois-ci et le mois dernier. Chaque montant reste
 * dans sa devise : un total qui melangerait des devises ne dirait rien.
 */
export function RevenueCard({ revenue }: Props) {
    const { t, locale } = useTranslation();

    const amounts = (rows: ConsoleAmountDue[]) =>
        rows.length === 0
            ? t('console.revenue.none')
            : rows
                  .map((row) => formatMoney(row.amount, row.currency, locale))
                  .join(' · ');

    const figures = [
        {
            label: t('console.revenue.recurring'),
            value: amounts(revenue.recurring),
        },
        {
            label: t('console.revenue.collected_this_month'),
            value: amounts(revenue.collectedThisMonth),
        },
        {
            label: t('console.revenue.collected_last_month'),
            value: amounts(revenue.collectedLastMonth),
        },
    ];

    return (
        <Card data-test="console-revenue">
            <CardHeader>
                <CardTitle>{t('console.revenue.title')}</CardTitle>
                <p className="text-muted-foreground text-sm">
                    {t('console.revenue.hint')}
                </p>
            </CardHeader>
            <CardContent className="space-y-4">
                <dl className="grid gap-4 sm:grid-cols-3">
                    {figures.map((figure) => (
                        <div key={figure.label}>
                            <dt className="text-muted-foreground text-sm">
                                {figure.label}
                            </dt>
                            <dd className="text-xl font-semibold">
                                {figure.value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <p className="text-sm">
                    {t('console.revenue.subscribers', {
                        count: revenue.subscribers,
                    })}
                    {revenue.byPlan.length > 0
                        ? ` ${revenue.byPlan
                              .map((row) => `${row.plan} : ${row.count}`)
                              .join(' · ')}`
                        : ''}
                </p>
            </CardContent>
        </Card>
    );
}
