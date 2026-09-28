import { Head } from '@inertiajs/react';
import { Check, Info, Pencil, X } from 'lucide-react';
import { PendingActionButton } from '@/components/console/pending-action-button';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount, formatMoney } from '@/lib/format-currency';
import { plans as plansRoute } from '@/routes/console';
import type { ConsolePlan, Translations } from '@/types';

type Props = {
    isSample: boolean;
    plans: ConsolePlan[];
};

/**
 * README ecran 30 : le catalogue des plans, prix et quotas. Les vrais plans de la base centrale,
 * en lecture seule jusqu'a l'etape 10.
 */
export default function Plans({ isSample, plans }: Props) {
    const { t, locale } = useTranslation();

    const price = (plan: ConsolePlan) => {
        if (plan.monthlyPrice === null) {
            return t('console.plans.on_quote');
        }

        if (plan.monthlyPrice === 0) {
            return t('console.plans.free');
        }

        return formatAmount(plan.monthlyPrice, locale);
    };

    const otherCurrencies = (plan: ConsolePlan) =>
        [
            plan.monthlyPriceEur
                ? formatMoney(plan.monthlyPriceEur, 'EUR', locale)
                : null,
            plan.monthlyPriceUsd
                ? formatMoney(plan.monthlyPriceUsd, 'USD', locale)
                : null,
        ]
            .filter((value) => value !== null)
            .join(' · ');

    const quota = (value: number | null) =>
        value === null
            ? t('console.plans.unlimited')
            : new Intl.NumberFormat(locale).format(value);

    const feature = (included: boolean, label: string) => (
        <li className="flex items-center gap-2">
            {included ? (
                <Check className="size-4 shrink-0" aria-hidden />
            ) : (
                <X
                    className="text-muted-foreground size-4 shrink-0"
                    aria-hidden
                />
            )}
            <span className={included ? undefined : 'text-muted-foreground'}>
                {label}
                <span className="sr-only">
                    {' '}
                    (
                    {included
                        ? t('console.plans.included')
                        : t('console.plans.not_included')}
                    )
                </span>
            </span>
        </li>
    );

    return (
        <>
            <Head title={t('console.plans.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.plans.title')}
                    description={t('console.plans.description')}
                />

                <div className="bg-muted text-muted-foreground flex items-start gap-3 rounded-lg p-3 text-sm">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>{t('console.plans.read_only')}</p>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    {plans.map((plan) => (
                        <Card key={plan.code}>
                            <CardHeader>
                                <CardTitle>{plan.name}</CardTitle>
                                <p className="text-2xl font-semibold">
                                    {price(plan)}
                                    {plan.monthlyPrice ? (
                                        <span className="text-muted-foreground text-sm font-normal">
                                            {' '}
                                            {t('console.plans.per_month')}
                                        </span>
                                    ) : null}
                                </p>
                                {otherCurrencies(plan) && (
                                    <p className="text-muted-foreground text-sm">
                                        {otherCurrencies(plan)}
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-4 text-sm">
                                <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-y-1">
                                    <dt className="text-muted-foreground">
                                        {t(
                                            'console.plans.quotas.active_events',
                                        )}
                                    </dt>
                                    <dd>{quota(plan.maxActiveEvents)}</dd>
                                    <dt className="text-muted-foreground">
                                        {t(
                                            'console.plans.quotas.registrations',
                                        )}
                                    </dt>
                                    <dd>{quota(plan.maxRegistrations)}</dd>
                                    <dt className="text-muted-foreground">
                                        {t('console.plans.quotas.members')}
                                    </dt>
                                    <dd>{quota(plan.maxMembers)}</dd>
                                </dl>
                                <ul className="space-y-1">
                                    {feature(
                                        plan.hasReconciliation,
                                        t(
                                            'console.plans.features.reconciliation',
                                        ),
                                    )}
                                    {feature(
                                        plan.hasReports,
                                        t('console.plans.features.reports'),
                                    )}
                                </ul>
                            </CardContent>
                            <CardFooter>
                                <PendingActionButton
                                    icon={Pencil}
                                    label={t('console.plans.edit')}
                                />
                            </CardFooter>
                        </Card>
                    ))}
                </div>
            </div>
        </>
    );
}

Plans.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.plans.title'),
            href: plansRoute(),
        },
    ],
});
