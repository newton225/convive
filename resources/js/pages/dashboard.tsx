import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { ChannelsChart } from '@/components/dashboard/channels-chart';
import { GettingStartedCard } from '@/components/dashboard/getting-started-card';
import { HoldExpiryCard } from '@/components/dashboard/hold-expiry-card';
import { KpiCard } from '@/components/dashboard/kpi-card';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { RegistrationsChart } from '@/components/dashboard/registrations-chart';
import { TableOccupancy } from '@/components/dashboard/table-occupancy';
import Heading from '@/components/heading';
import { ProductTourButton } from '@/components/product-tour-button';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatRelative } from '@/lib/format-date';
import { can, Permission } from '@/lib/permissions';
import { dashboard } from '@/routes';
import { create as createEvent } from '@/routes/tenants/events';
import { index as proofsIndex } from '@/routes/tenants/events/proofs';
import { index as registrationsIndex } from '@/routes/tenants/events/registrations';
import type {
    DashboardInvitation,
    DashboardOverview,
    GettingStarted,
    LocaleCode,
    TranslationReplacements,
    Translations,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    paymentAccountNotice?: { changed: boolean; days: number } | null;
    overview?: DashboardOverview | null;
    gettingStarted?: GettingStarted | null;
};

const Kpis = [
    'registrations',
    'validated',
    'toCheck',
    'withoutProof',
    'seatsLeft',
] as const;

const KpiLabels: Record<(typeof Kpis)[number], string> = {
    registrations: 'dashboard.kpis.registrations',
    validated: 'dashboard.kpis.validated',
    toCheck: 'dashboard.kpis.to_check',
    withoutProof: 'dashboard.kpis.without_proof',
    seatsLeft: 'dashboard.kpis.seats_left',
};

// Le sous-titre de chaque chiffre cle (prototype Convive.dc.html) : tendance, argent encaisse,
// attente trop longue, prochaine purge.
function kpiHint(
    kpi: (typeof Kpis)[number],
    overview: DashboardOverview,
    t: (key: string, replacements?: TranslationReplacements) => string,
    locale: LocaleCode,
): string | null {
    const context = overview.context;

    switch (kpi) {
        case 'registrations':
            return t('dashboard.hints.this_week', {
                count: context.registrationsThisWeek,
            });
        case 'validated':
            return context.validatedShare === null
                ? null
                : t('dashboard.hints.validated', {
                      share: String(context.validatedShare),
                      amount: formatAmount(context.collectedAmount, locale),
                  });
        case 'toCheck':
            return context.waitingOver24h > 0
                ? t('dashboard.hints.waiting', {
                      count: context.waitingOver24h,
                  })
                : null;
        case 'withoutProof':
            return context.purgeAt
                ? t('dashboard.hints.purge', {
                      when: formatRelative(context.purgeAt, locale),
                  })
                : null;
        default:
            return null;
    }
}

/**
 * README ecran 17 : le tableau de bord. Inscrits, preuves validees, preuves a verifier, sans
 * preuve, places restantes, inscriptions par jour, preuves par canal, occupation des tables,
 * activite recente. Toujours pour un seul evenement (le plus proche encore ouvert, sinon le plus
 * recent, voir `App\Support\DashboardOverview`) ; `overview` vaut `null` quand l'organisation n'a
 * encore aucun evenement.
 */
export default function Dashboard({
    pendingInvitations = [],
    paymentAccountNotice = null,
    overview = null,
    gettingStarted = null,
}: Props) {
    const { t, locale } = useTranslation();
    const { currentTenant, tenantPermissions } = usePage().props;
    const canCheckProofs =
        tenantPermissions !== null &&
        can(tenantPermissions, Permission.ProofsView);
    const canSeeRegistrations =
        tenantPermissions !== null &&
        can(tenantPermissions, Permission.RegistrationsView);
    const [showInvitations, setShowInvitations] = useState(
        pendingInvitations.length > 0,
    );

    return (
        <>
            <Head title={t('dashboard.title')} />
            <PendingInvitationsModal
                invitations={pendingInvitations}
                open={pendingInvitations.length > 0 && showInvitations}
                onOpenChange={setShowInvitations}
            />

            <div className="flex flex-1 flex-col gap-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        title={t('dashboard.title')}
                        description={
                            overview
                                ? `${t('dashboard.event_label')} : ${overview.eventName}`
                                : undefined
                        }
                    />
                    <div className="flex flex-wrap items-center gap-2">
                        {currentTenant ? (
                            <ProductTourButton tour="welcome" />
                        ) : null}
                        {overview ? (
                            <div className="flex flex-wrap items-center gap-2">
                                {overview.context.daysUntilEvent !== null ? (
                                    <Badge
                                        variant="secondary"
                                        data-test="dashboard-countdown"
                                    >
                                        {t('dashboard.countdown', {
                                            days: overview.context
                                                .daysUntilEvent,
                                            count: overview.context
                                                .daysUntilEvent,
                                        })}
                                    </Badge>
                                ) : null}
                                {canCheckProofs &&
                                currentTenant &&
                                overview.kpis.toCheck > 0 ? (
                                    <Button
                                        asChild
                                        data-test="dashboard-check-proofs"
                                    >
                                        <Link
                                            href={proofsIndex([
                                                currentTenant.slug,
                                                overview.eventId,
                                            ])}
                                        >
                                            {t('dashboard.check_proofs', {
                                                count: overview.kpis.toCheck,
                                            })}
                                        </Link>
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                    </div>
                </div>

                {paymentAccountNotice ? (
                    <p
                        className="bg-card flex items-start gap-2 rounded-xl p-3 text-sm"
                        data-test="dashboard-payment-account-notice"
                    >
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        {t('payment_accounts.notice.recent_change', {
                            days: paymentAccountNotice.days,
                        })}
                    </p>
                ) : null}

                {/* README 2.11 : signale tant qu'une somme reste a rendre. */}
                {overview?.refundsDue ? (
                    <div
                        className="bg-card flex flex-wrap items-center gap-x-3 gap-y-2 rounded-xl p-3 text-sm"
                        data-test="dashboard-refunds-due"
                    >
                        <AlertTriangle className="text-destructive h-4 w-4 shrink-0" />
                        <span className="font-medium">
                            {t('dashboard.refunds_due', {
                                count: overview.refundsDue.count,
                                amount: formatAmount(
                                    overview.refundsDue.amount,
                                    locale,
                                ),
                            })}
                        </span>
                        {canSeeRegistrations && currentTenant ? (
                            <Button asChild variant="outline" size="sm">
                                <Link
                                    href={registrationsIndex([
                                        currentTenant.slug,
                                        overview.eventId,
                                    ])}
                                >
                                    {t('dashboard.refunds_due_action')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                ) : null}

                {gettingStarted && currentTenant ? (
                    <GettingStartedCard
                        tenantSlug={currentTenant.slug}
                        gettingStarted={gettingStarted}
                    />
                ) : null}

                {overview ? (
                    <>
                        <div
                            className="grid grid-cols-2 gap-3 lg:grid-cols-5"
                            data-test="dashboard-kpis"
                        >
                            {Kpis.map((kpi) => (
                                <KpiCard
                                    key={kpi}
                                    label={t(KpiLabels[kpi])}
                                    value={overview.kpis[kpi]}
                                    hint={kpiHint(kpi, overview, t, locale)}
                                    testId={`dashboard-kpi-${kpi}`}
                                />
                            ))}
                        </div>

                        <HoldExpiryCard expiry={overview.holdExpiry} />

                        <div className="grid gap-4 lg:grid-cols-2">
                            <RegistrationsChart
                                data={overview.registrationsPerDay}
                            />
                            <ChannelsChart data={overview.proofsByChannel} />
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                            <TableOccupancy tables={overview.tableOccupancy} />
                            <RecentActivity
                                activity={overview.recentActivity}
                            />
                        </div>
                    </>
                ) : gettingStarted ? null : (
                    <div
                        className="bg-card space-y-3 rounded-xl p-6 text-center"
                        data-test="dashboard-empty"
                    >
                        <p className="font-medium">
                            {t('dashboard.empty.title')}
                        </p>
                        <p className="text-muted-foreground text-sm">
                            {t('dashboard.empty.description')}
                        </p>
                        <Button asChild>
                            <Link
                                href={
                                    currentTenant
                                        ? createEvent(currentTenant.slug)
                                        : '/settings/tenants'
                                }
                            >
                                {t('dashboard.empty.cta')}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (props: {
    currentTenant?: { slug: string } | null;
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'navigation.dashboard'),
            href: props.currentTenant
                ? dashboard(props.currentTenant.slug)
                : '/',
        },
    ],
});
