import { Head } from '@inertiajs/react';
import { DownloadButton } from '@/components/download-button';
import Heading from '@/components/heading';
import { TreasuryAccountsTable } from '@/components/treasury/treasury-accounts-table';
import { TreasuryEventsTable } from '@/components/treasury/treasury-events-table';
import { TreasuryPeriodFilter } from '@/components/treasury/treasury-period-filter';
import { TreasuryRefundsDue } from '@/components/treasury/treasury-refunds-due';
import { TreasuryTableExport } from '@/components/treasury/treasury-table-export';
import { TreasuryTotalsCards } from '@/components/treasury/treasury-totals-cards';
import { translate, useTranslation } from '@/hooks/use-translation';
import { can, Permission } from '@/lib/permissions';
import { treasury as treasuryRoute } from '@/routes/tenants';
import { pdf as exportPdf } from '@/routes/tenants/treasury/export';
import type {
    TreasuryAccountRow,
    TreasuryEventRow,
    TreasuryFilters,
    TreasuryRefundDue,
    TreasuryTotals,
    TenantPermissions,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string };
    permissions: TenantPermissions;
    filters: TreasuryFilters;
    totals: TreasuryTotals;
    events: TreasuryEventRow[];
    accounts: TreasuryAccountRow[];
    refundsDue: TreasuryRefundDue[];
    refundsDueCount: number;
    refundListLimit: number;
};

/**
 * L'ecran Tresorerie (decision du 2026-10-16) : les totaux de toute l'organisation sur une periode,
 * par evenement et par compte de versement, et les remboursements a faire. Les chiffres sont ceux des
 * rapports d'evenement, additionnes : rien n'est stocke, tout se recalcule.
 */
export default function Treasury({
    tenant,
    permissions,
    filters,
    totals,
    events,
    accounts,
    refundsDue,
    refundsDueCount,
    refundListLimit,
}: Props) {
    const { t } = useTranslation();
    const canExport = can(permissions, Permission.ReportsExport);
    const exportOf = (table: 'events' | 'accounts' | 'refunds') =>
        canExport ? (
            <TreasuryTableExport
                tenantSlug={tenant.slug}
                table={table}
                filters={filters}
            />
        ) : undefined;

    return (
        <>
            <Head title={t('treasury.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={t('treasury.title')}
                        description={t('treasury.description')}
                    />

                    {canExport ? (
                        <DownloadButton
                            variant="outline"
                            href={
                                exportPdf(tenant.slug, {
                                    query: {
                                        from: filters.from ?? undefined,
                                        to: filters.to ?? undefined,
                                    },
                                }).url
                            }
                            data-test="treasury-export-pdf"
                        >
                            {t('treasury.export.pdf')}
                        </DownloadButton>
                    ) : null}
                </div>

                <TreasuryPeriodFilter
                    tenantSlug={tenant.slug}
                    filters={filters}
                />
                <TreasuryTotalsCards totals={totals} />
                <TreasuryEventsTable
                    rows={events}
                    actions={exportOf('events')}
                />
                <TreasuryAccountsTable
                    rows={accounts}
                    actions={exportOf('accounts')}
                />
                <TreasuryRefundsDue
                    rows={refundsDue}
                    count={refundsDueCount}
                    limit={refundListLimit}
                    actions={exportOf('refunds')}
                />
            </div>
        </>
    );
}

Treasury.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'treasury.title'),
            href: treasuryRoute(props.tenant.slug),
        },
    ],
});
