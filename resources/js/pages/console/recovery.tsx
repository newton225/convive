import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ConsoleTable } from '@/components/console/console-table';
import { RevenueCard } from '@/components/console/revenue-card';
import { RemindButton } from '@/components/console/remind-button';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Card, CardContent } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format-currency';
import { formatDate } from '@/lib/format-date';
import { recovery } from '@/routes/console';
import { show } from '@/routes/console/organisations';
import type {
    ConsoleAmountDue,
    ConsoleRevenue,
    ConsoleFailedPayment,
    ConsoleUnpaid,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    unpaid: ConsoleUnpaid[];
    amountsDue: ConsoleAmountDue[];
    failedPayments: ConsoleFailedPayment[];
    revenue: ConsoleRevenue;
};

/**
 * README ecran 29 : le recouvrement. Impayes en cours, relances envoyees, suspensions a venir a
 * J+10 (README section 3, Facturation) et paiements en echec.
 */
export default function Recovery({
    isSample,
    unpaid,
    amountsDue,
    failedPayments,
    revenue,
}: Props) {
    const { t, locale } = useTranslation();

    const pastDueCount = unpaid.filter(
        (row) => row.status === 'past_due',
    ).length;
    const suspendedCount = unpaid.filter(
        (row) => row.status === 'suspended',
    ).length;

    const organisationLink = (slug: string, name: string) => (
        <Link
            href={show(slug)}
            className="font-medium underline-offset-4 hover:underline"
        >
            {name}
        </Link>
    );

    const unpaidColumns: ColumnDef<ConsoleUnpaid>[] = [
        {
            header: t('console.recovery.unpaid_columns.organisation'),
            cell: ({ row }) =>
                organisationLink(row.original.slug, row.original.name),
        },
        {
            header: t('console.recovery.unpaid_columns.plan'),
            accessorKey: 'planName',
        },
        {
            header: t('console.recovery.unpaid_columns.amount'),
            cell: ({ row }) =>
                formatMoney(row.original.amount, row.original.currency, locale),
        },
        {
            header: t('console.recovery.unpaid_columns.since'),
            cell: ({ row }) =>
                row.original.pastDueSince
                    ? formatDate(row.original.pastDueSince, locale)
                    : '',
        },
        {
            header: t('console.recovery.unpaid_columns.reminders'),
            cell: ({ row }) =>
                t('console.recovery.reminders_count', {
                    count: row.original.remindersSent,
                }),
        },
        {
            header: t('console.recovery.unpaid_columns.suspension'),
            cell: ({ row }) =>
                row.original.suspendsAt ? (
                    t('console.recovery.suspends_on', {
                        date: formatDate(row.original.suspendsAt, locale),
                    })
                ) : (
                    <span className="text-muted-foreground">
                        {t('console.recovery.already_suspended')}
                    </span>
                ),
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) =>
                row.original.status === 'past_due' ? (
                    <RemindButton slug={row.original.slug} />
                ) : null,
        },
    ];

    const failedColumns: ColumnDef<ConsoleFailedPayment>[] = [
        {
            header: t('console.recovery.failed_columns.organisation'),
            cell: ({ row }) =>
                organisationLink(row.original.slug, row.original.name),
        },
        {
            header: t('console.recovery.failed_columns.date'),
            cell: ({ row }) => formatDate(row.original.at, locale),
        },
        {
            header: t('console.recovery.failed_columns.amount'),
            cell: ({ row }) =>
                formatMoney(row.original.amount, row.original.currency, locale),
        },
    ];

    const summary = [
        {
            label: t('console.recovery.summary.past_due'),
            value: String(pastDueCount),
        },
        {
            label: t('console.recovery.summary.suspended'),
            value: String(suspendedCount),
        },
        {
            label: t('console.recovery.summary.amount_due'),
            value:
                amountsDue.length === 0
                    ? t('console.recovery.nothing_due')
                    : amountsDue
                          .map((due) =>
                              formatMoney(due.amount, due.currency, locale),
                          )
                          .join(' · '),
        },
    ];

    return (
        <>
            <Head title={t('console.recovery.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.recovery.title')}
                    description={t('console.recovery.description')}
                />

                <RevenueCard revenue={revenue} />

                <div className="grid gap-4 sm:grid-cols-3">
                    {summary.map((item) => (
                        <Card key={item.label}>
                            <CardContent>
                                <p className="text-muted-foreground text-sm">
                                    {item.label}
                                </p>
                                <p className="text-2xl font-semibold">
                                    {item.value}
                                </p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.recovery.unpaid')}
                    </h3>
                    <ConsoleTable
                        columns={unpaidColumns}
                        data={unpaid}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.recovery.unpaid_empty')}
                            </p>
                        }
                    />
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.recovery.failed')}
                    </h3>
                    <ConsoleTable
                        columns={failedColumns}
                        data={failedPayments}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.recovery.failed_empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

Recovery.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.recovery.title'),
            href: recovery(),
        },
    ],
});
