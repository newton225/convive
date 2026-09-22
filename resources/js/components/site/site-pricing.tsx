import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format-currency';
import { cn } from '@/lib/utils';
import { register } from '@/routes';
import type { SitePlan } from '@/types';
import { Reveal } from './reveal';

type Props = {
    plans: SitePlan[];
    currency: string;
};

/**
 * Les tarifs, avec un palier mis en avant. Les plans et leurs prix viennent de la base (la meme
 * source que l'ecran d'abonnement) : ce composant n'en invente aucun.
 */
export function SitePricing({ plans, currency }: Props) {
    const { t, locale } = useTranslation();

    const limit = (value: number | null) =>
        value === null ? t('site.pricing.unlimited') : String(value);

    const price = (plan: SitePlan) => {
        const value = plan.prices[currency] ?? null;

        return value === null
            ? t('site.pricing.on_quote')
            : value === 0
              ? t('site.pricing.free')
              : t('site.pricing.per_month', {
                    price: formatMoney(value, currency, locale),
                });
    };

    return (
        <section
            id="pricing"
            className="mx-auto w-full max-w-6xl scroll-mt-20 px-6 py-16"
            data-test="site-pricing"
        >
            <Reveal>
                <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {t('site.pricing.title')}
                </h2>
                <p className="text-muted-foreground mt-3">
                    {t('site.pricing.subtitle')}
                </p>
            </Reveal>

            <div className="mt-10 grid items-stretch gap-4 md:grid-cols-3">
                {plans.map((plan, index) => (
                    <Reveal
                        key={plan.code}
                        delay={index * 0.08}
                        className="flex"
                    >
                        <article
                            className={cn(
                                'flex w-full flex-col gap-5 rounded-2xl p-6',
                                plan.highlighted
                                    ? 'bg-foreground text-background'
                                    : 'bg-card',
                            )}
                            data-test={`site-plan-${plan.code}`}
                        >
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-lg font-semibold">
                                    {plan.name}
                                </h3>
                                {plan.highlighted ? (
                                    <span className="bg-primary text-primary-foreground rounded-full px-2.5 py-0.5 text-xs font-medium">
                                        {t('site.pricing.recommended')}
                                    </span>
                                ) : null}
                            </div>

                            <p className="text-2xl font-semibold tracking-tight">
                                {price(plan)}
                            </p>

                            <ul
                                className={cn(
                                    'space-y-2 text-sm',
                                    plan.highlighted
                                        ? 'text-background/80'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {[
                                    t('site.pricing.events', {
                                        count: limit(plan.maxActiveEvents),
                                    }),
                                    t('site.pricing.registrations', {
                                        count: limit(plan.maxRegistrations),
                                    }),
                                    t('site.pricing.members', {
                                        count: limit(plan.maxMembers),
                                    }),
                                    plan.hasReconciliation
                                        ? t('site.pricing.reconciliation')
                                        : null,
                                    plan.hasReports
                                        ? t('site.pricing.reports')
                                        : null,
                                    plan.hasCustomDomain
                                        ? t('site.pricing.custom_domain')
                                        : null,
                                    plan.hasSso ? t('site.pricing.sso') : null,
                                ]
                                    .filter(
                                        (line): line is string => line !== null,
                                    )
                                    .map((line) => (
                                        <li key={line} className="flex gap-2">
                                            <Check className="mt-0.5 size-4 shrink-0" />
                                            {line}
                                        </li>
                                    ))}
                            </ul>

                            {plan.prices[currency] !== null ? (
                                <Button
                                    className="mt-auto"
                                    variant={
                                        plan.highlighted ? 'default' : 'outline'
                                    }
                                    asChild
                                >
                                    <Link href={register()}>
                                        {t('site.pricing.choose')}
                                    </Link>
                                </Button>
                            ) : null}
                        </article>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
