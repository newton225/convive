import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ArrowLeft, Info } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { OrganisationStatusBadge } from '@/components/console/organisation-status-badge';
import { OrganisationActions } from '@/components/console/organisation-actions';
import { QuotaUsage } from '@/components/console/quota-usage';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatMoney } from '@/lib/format-currency';
import { formatDate, formatDateTime } from '@/lib/format-date';
import { index, show } from '@/routes/console/organisations';
import type {
    ConsoleOrganisationDetails,
    ConsolePlanOption,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    organisation: ConsoleOrganisationDetails;
    plans: ConsolePlanOption[];
    // Faux pour un profil editeur qui lit les organisations sans pouvoir agir (Support).
    canAct: boolean;
};

type Invoice = ConsoleOrganisationDetails['invoices'][number];

/**
 * README ecran 28 : la fiche d'une organisation cliente. Identite, abonnement, consommation,
 * factures, acces de support en cours et actions de l'editeur. Le contenu de l'organisation n'y
 * figure pas : il ne se lit qu'avec un acces de support qu'elle a ouvert.
 */
export default function Organisation({
    isSample,
    organisation,
    plans,
    canAct,
}: Props) {
    const { t, locale } = useTranslation();

    const invoiceColumns: ColumnDef<Invoice>[] = [
        {
            header: t('console.organisation.invoice_columns.number'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">{row.original.number}</span>
            ),
        },
        {
            header: t('console.organisation.invoice_columns.issued_at'),
            cell: ({ row }) => formatDate(row.original.issuedAt, locale),
        },
        {
            header: t('console.organisation.invoice_columns.amount'),
            cell: ({ row }) =>
                formatMoney(row.original.amount, row.original.currency, locale),
        },
        {
            header: t('console.organisation.invoice_columns.status'),
            cell: ({ row }) => (
                <Badge
                    variant={
                        row.original.status === 'paid'
                            ? 'secondary'
                            : 'destructive'
                    }
                >
                    {t(`billing.invoice_statuses.${row.original.status}`)}
                </Badge>
            ),
        },
    ];

    const facts: { label: string; value: string }[] = [
        {
            label: t('console.organisation.plan'),
            value: organisation.planName,
        },
        ...(organisation.trialEndsAt
            ? [
                  {
                      label: t('console.organisation.trial_ends'),
                      value: formatDate(organisation.trialEndsAt, locale),
                  },
              ]
            : []),
        ...(organisation.pastDueSince
            ? [
                  {
                      label: t('console.organisation.past_due_since'),
                      value: formatDate(organisation.pastDueSince, locale),
                  },
              ]
            : []),
        ...(organisation.suspendedAt
            ? [
                  {
                      label: t('console.organisation.suspended_at'),
                      value: formatDate(organisation.suspendedAt, locale),
                  },
              ]
            : []),
        ...(organisation.deletionAt
            ? [
                  {
                      label: t('console.organisation.deletion_at'),
                      value: formatDate(organisation.deletionAt, locale),
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title={organisation.name} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Link
                    href={index()}
                    className="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1 text-sm"
                >
                    <ArrowLeft className="size-4" />
                    {t('console.organisation.back')}
                </Link>

                <div className="flex flex-wrap items-center gap-3">
                    <Heading title={organisation.name} variant="small" />
                    <OrganisationStatusBadge status={organisation.status} />
                </div>

                <div className="bg-muted text-muted-foreground flex items-start gap-3 rounded-lg p-3 text-sm">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>{t('console.organisation.metadata_notice')}</p>
                </div>

                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('console.organisation.identity')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                                <dt className="text-muted-foreground">
                                    {t('console.organisation.legal_name')}
                                </dt>
                                <dd>{organisation.legalName}</dd>
                                <dt className="text-muted-foreground">
                                    {t('console.organisation.contact_email')}
                                </dt>
                                <dd className="break-all">
                                    {organisation.contactEmail}
                                </dd>
                                <dt className="text-muted-foreground">
                                    {t('console.organisation.subdomain')}
                                </dt>
                                <dd className="font-mono text-xs">
                                    {organisation.subdomain}
                                </dd>
                                <dt className="text-muted-foreground">
                                    {t('console.organisation.opened_at')}
                                </dt>
                                <dd>
                                    {formatDate(organisation.openedAt, locale)}
                                </dd>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('console.organisation.subscription')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <dl className="grid grid-cols-[minmax(0,auto)_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                                {facts.map((fact) => (
                                    <div key={fact.label} className="contents">
                                        <dt className="text-muted-foreground">
                                            {fact.label}
                                        </dt>
                                        <dd>{fact.value}</dd>
                                    </div>
                                ))}
                            </dl>
                            <ul className="text-muted-foreground space-y-1 text-sm">
                                {organisation.history.map((item) => (
                                    <li key={`${item.type}-${item.at}`}>
                                        {formatDate(item.at, locale)} :{' '}
                                        {t(
                                            `console.organisation.history_types.${item.type}`,
                                            { detail: item.detail ?? '' },
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('console.organisation.usage')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('console.usage.active_events')}
                                </dt>
                                <dd className="text-base">
                                    <QuotaUsage
                                        quota={organisation.usage.activeEvents}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('console.usage.registrations')}
                                </dt>
                                <dd className="text-base">
                                    <QuotaUsage
                                        quota={organisation.usage.registrations}
                                    />
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    {t('console.usage.members')}
                                </dt>
                                <dd className="text-base">
                                    <QuotaUsage
                                        quota={organisation.usage.members}
                                    />
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('console.organisation.actions')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {canAct ? (
                            <OrganisationActions
                                organisation={organisation}
                                plans={plans}
                            />
                        ) : (
                            <p className="text-muted-foreground text-sm">
                                {t('console.organisation.actions_not_allowed')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>
                            {t('console.organisation.support')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="text-sm">
                        {organisation.supportAccess ? (
                            <p>
                                {t('console.organisation.support_active', {
                                    operator:
                                        organisation.supportAccess.operator,
                                    granted_by:
                                        organisation.supportAccess.grantedBy ??
                                        '',
                                    expires: formatDateTime(
                                        organisation.supportAccess.expiresAt,
                                        locale,
                                    ),
                                })}
                            </p>
                        ) : (
                            <p className="text-muted-foreground">
                                {t('console.organisation.support_none')}
                            </p>
                        )}
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.organisation.invoices')}
                    </h3>
                    <ConsoleTable
                        columns={invoiceColumns}
                        data={organisation.invoices}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.organisation.invoices_empty')}
                            </p>
                        }
                    />
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.organisation.console_actions')}
                    </h3>
                    {organisation.consoleActions.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            {t('console.organisation.console_actions_empty')}
                        </p>
                    ) : (
                        <ul className="space-y-1 text-sm">
                            {organisation.consoleActions.map((action) => (
                                <li key={`${action.type}-${action.at}`}>
                                    <span className="text-muted-foreground">
                                        {formatDateTime(action.at, locale)} :
                                    </span>{' '}
                                    {t(
                                        `console.audit.messages.${action.type}`,
                                        {
                                            actor:
                                                action.actor ??
                                                t('console.system_actor'),
                                            organisation: organisation.name,
                                        },
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

Organisation.layout = (props: {
    translations: Translations;
    organisation: ConsoleOrganisationDetails;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.organisations.title'),
            href: index(),
        },
        {
            title: props.organisation.name,
            href: show(props.organisation.slug),
        },
    ],
});
