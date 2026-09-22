import { Head, Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { useState } from 'react';
import { ChannelsChart } from '@/components/dashboard/channels-chart';
import { KpiCard } from '@/components/dashboard/kpi-card';
import { RecentActivity } from '@/components/dashboard/recent-activity';
import { RegistrationsChart } from '@/components/dashboard/registrations-chart';
import { TableOccupancy } from '@/components/dashboard/table-occupancy';
import Heading from '@/components/heading';
import PendingInvitationsModal from '@/components/pending-invitations-modal';
import { Button } from '@/components/ui/button';
import { translate, useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';
import { create as createEvent } from '@/routes/tenants/events';
import type {
    DashboardInvitation,
    DashboardOverview,
    Translations,
} from '@/types';

type Props = {
    pendingInvitations?: DashboardInvitation[];
    paymentAccountNotice?: { changed: boolean; days: number } | null;
    overview?: DashboardOverview | null;
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
}: Props) {
    const { t } = useTranslation();
    const { currentTenant } = usePage().props;
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

            <div className="flex flex-1 flex-col gap-6 p-4">
                <Heading
                    title={t('dashboard.title')}
                    description={
                        overview
                            ? `${t('dashboard.event_label')} : ${overview.eventName}`
                            : undefined
                    }
                />

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
                                    testId={`dashboard-kpi-${kpi}`}
                                />
                            ))}
                        </div>

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
                ) : (
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
