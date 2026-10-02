import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import { show as billingShow } from '@/routes/tenants/billing';

/**
 * Le plan de l'organisation courante et son usage principal, en pied de menu (prototype
 * Convive.dc.html : « Plan Association · 2 evenements actifs sur 5 »). Renvoie vers l'abonnement
 * pour qui peut le consulter. Masque quand le menu est replie en icones.
 */
export function PlanUsage() {
    const { t } = useTranslation();
    const { currentPlan, currentTenant, tenantPermissions } = usePage().props;

    if (!currentPlan || !currentTenant) {
        return null;
    }

    const usage =
        currentPlan.maxActiveEvents === null
            ? t('billing.sidebar.events_unlimited', {
                  count: currentPlan.activeEvents,
              })
            : t('billing.sidebar.events', {
                  count: currentPlan.activeEvents,
                  max: currentPlan.maxActiveEvents,
              });

    const content = (
        <>
            <span className="block font-medium">
                {t('billing.sidebar.plan', { name: currentPlan.name })}
            </span>
            <span className="text-muted-foreground block">{usage}</span>
            {currentPlan.trialDaysLeft !== null ? (
                <span className="block" data-test="sidebar-trial-days">
                    {t('billing.sidebar.trial_days', {
                        count: currentPlan.trialDaysLeft,
                    })}
                </span>
            ) : null}
        </>
    );

    const className =
        'block rounded-lg px-2 py-1.5 text-xs group-data-[collapsible=icon]:hidden';

    return tenantPermissions !== null &&
        can(tenantPermissions, Permission.BillingView) ? (
        <Link
            href={billingShow(currentTenant.slug)}
            className={`${className} hover:bg-sidebar-accent`}
            data-test="sidebar-plan-usage"
        >
            {content}
        </Link>
    ) : (
        <div className={className} data-test="sidebar-plan-usage">
            {content}
        </div>
    );
}
