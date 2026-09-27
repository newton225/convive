import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { edit as tenantEdit } from '@/routes/tenants';
import {
    create as createEvent,
    index as eventsIndex,
} from '@/routes/tenants/events';
import { edit as organisationEdit } from '@/routes/tenants/organisation';
import { index as paymentAccountsIndex } from '@/routes/tenants/payment-accounts';
import type { GettingStarted, GettingStartedStepKey } from '@/types';

type Props = {
    tenantSlug: string;
    gettingStarted: GettingStarted;
};

const destinations: Record<GettingStartedStepKey, (slug: string) => string> = {
    identity: (slug) => organisationEdit(slug).url,
    payment_account: (slug) => paymentAccountsIndex(slug).url,
    event: (slug) => createEvent(slug).url,
    publish: (slug) => eventsIndex(slug).url,
    team: (slug) => tenantEdit(slug).url,
};

/**
 * « Premiers pas » : ce qu'il reste a faire pour publier un premier evenement, dans l'ordre.
 * Seule la prochaine etape porte un bouton, pour que le regard sache ou aller.
 */
export function GettingStartedCard({ tenantSlug, gettingStarted }: Props) {
    const { t } = useTranslation();
    const { steps, completed } = gettingStarted;
    const next = steps.find((step) => !step.done);

    return (
        <section
            className="bg-card rounded-xl border p-5"
            data-test="dashboard-getting-started"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="font-semibold">
                    {t('dashboard.getting_started.title')}
                </h2>
                <span className="text-muted-foreground text-sm tabular-nums">
                    {t('dashboard.getting_started.progress', {
                        done: completed,
                        total: steps.length,
                    })}
                </span>
            </div>
            <p className="text-muted-foreground mt-1 text-sm">
                {t('dashboard.getting_started.description')}
            </p>

            <ol className="mt-4 space-y-2">
                {steps.map((step, index) => {
                    const isNext = step.key === next?.key;

                    return (
                        <li
                            key={step.key}
                            className={cn(
                                'flex flex-wrap items-center gap-3 rounded-lg p-2',
                                isNext && 'bg-muted',
                            )}
                            data-test={`getting-started-${step.key}`}
                        >
                            <span
                                className={cn(
                                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-medium',
                                    step.done
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border',
                                )}
                            >
                                {step.done ? (
                                    <Check className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span className="min-w-0 flex-1">
                                <span
                                    className={cn(
                                        'block text-sm',
                                        step.done
                                            ? 'text-muted-foreground'
                                            : 'font-medium',
                                    )}
                                >
                                    {step.done ? (
                                        <span className="sr-only">
                                            {t(
                                                'dashboard.getting_started.done',
                                            )}
                                            {' : '}
                                        </span>
                                    ) : null}
                                    {t(
                                        `dashboard.getting_started.steps.${step.key}.title`,
                                    )}
                                </span>
                                {isNext ? (
                                    <span className="text-muted-foreground block text-xs">
                                        {t(
                                            `dashboard.getting_started.steps.${step.key}.hint`,
                                        )}
                                    </span>
                                ) : null}
                            </span>
                            {isNext ? (
                                <Button size="sm" asChild>
                                    <Link
                                        href={destinations[step.key](
                                            tenantSlug,
                                        )}
                                    >
                                        {t(
                                            `dashboard.getting_started.steps.${step.key}.action`,
                                        )}
                                    </Link>
                                </Button>
                            ) : null}
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
