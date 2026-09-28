import { Head, Link } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useMemo, useState } from 'react';
import { ConsoleTable } from '@/components/console/console-table';
import { OrganisationStatusBadge } from '@/components/console/organisation-status-badge';
import { QuotaUsage } from '@/components/console/quota-usage';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDate, formatRelative } from '@/lib/format-date';
import { index, show } from '@/routes/console/organisations';
import type {
    ConsoleOrganisationStatus,
    ConsoleOrganisationSummary,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    organisations: ConsoleOrganisationSummary[];
};

const AllStatuses = 'all';

const statuses: ConsoleOrganisationStatus[] = [
    'trial',
    'active',
    'past_due',
    'suspended',
    'deletion_scheduled',
];

/**
 * README ecran 27 : les organisations clientes, leur plan, leur etat et leur consommation. Des
 * metadonnees seulement, jamais le contenu d'une organisation (README section 3).
 */
export default function Organisations({ isSample, organisations }: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState<string>(AllStatuses);

    // PROVISOIRE : filtre en memoire sur le jeu d'exemple ; passe cote serveur
    // (`spatie/laravel-query-builder`) quand la liste viendra de la base.
    const visible = useMemo(() => {
        const needle = search.trim().toLocaleLowerCase(locale);

        return organisations.filter(
            (organisation) =>
                (status === AllStatuses || organisation.status === status) &&
                (needle === '' ||
                    organisation.name
                        .toLocaleLowerCase(locale)
                        .includes(needle)),
        );
    }, [organisations, search, status, locale]);

    const columns: ColumnDef<ConsoleOrganisationSummary>[] = [
        {
            header: t('console.organisations.columns.name'),
            cell: ({ row }) => (
                <Link
                    href={show(row.original.slug)}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.original.name}
                </Link>
            ),
        },
        {
            header: t('console.organisations.columns.plan'),
            accessorKey: 'planName',
        },
        {
            header: t('console.organisations.columns.status'),
            cell: ({ row }) => (
                <OrganisationStatusBadge status={row.original.status} />
            ),
        },
        {
            header: t('console.organisations.columns.registrations'),
            cell: ({ row }) => (
                <QuotaUsage quota={row.original.usage.registrations} />
            ),
        },
        {
            header: t('console.organisations.columns.opened'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {formatDate(row.original.openedAt, locale)}
                </span>
            ),
        },
        {
            header: t('console.organisations.columns.last_activity'),
            cell: ({ row }) => (
                <span className="text-muted-foreground whitespace-nowrap">
                    {formatRelative(row.original.lastActivityAt, locale)}
                </span>
            ),
        },
    ];

    const emptyState =
        organisations.length === 0 ? (
            <>
                <p className="font-medium">
                    {t('console.organisations.empty.title')}
                </p>
                <p className="text-muted-foreground text-sm">
                    {t('console.organisations.empty.description')}
                </p>
            </>
        ) : (
            <>
                <p className="font-medium">
                    {t('console.organisations.no_match.title')}
                </p>
                <p className="text-muted-foreground text-sm">
                    {t('console.organisations.no_match.description')}
                </p>
            </>
        );

    return (
        <>
            <Head title={t('console.organisations.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.organisations.title')}
                    description={t('console.organisations.description')}
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t(
                            'console.organisations.search_placeholder',
                        )}
                        aria-label={t(
                            'console.organisations.search_placeholder',
                        )}
                        className="max-w-xs"
                    />
                    <Select value={status} onValueChange={setStatus}>
                        <SelectTrigger className="w-60">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={AllStatuses}>
                                {t('console.organisations.filter_all')}
                            </SelectItem>
                            {statuses.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {t(`console.statuses.${item}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <ConsoleTable
                    columns={columns}
                    data={visible}
                    emptyState={emptyState}
                />
            </div>
        </>
    );
}

Organisations.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.organisations.title'),
            href: index(),
        },
    ],
});
