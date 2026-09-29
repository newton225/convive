import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useEffect, useState } from 'react';
import DataTable from '@/components/data-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { sortingFromParam, sortingToParam } from '@/lib/server-sorting';
import { index } from '@/routes/tenants/audit';
import type {
    AuditEntry,
    AuditFilters,
    AuditMeta,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    entries: AuditEntry[];
    meta: AuditMeta;
    filters: AuditFilters;
    types: string[];
};

const AllTypes = 'all';

/**
 * README ecran 23 : la journalisation. Date, type, message, acteur, adresse IP ; conservation 24
 * mois, en ecriture seule. Recherche, filtre de type, tri et pagination cote serveur
 * (`spatie/laravel-query-builder`), comme la base d'inscrits.
 */
export default function Audit({
    tenant,
    entries,
    meta,
    filters,
    types,
}: Props) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search ?? '');

    const message = (entry: AuditEntry) =>
        t(`audit.messages.${entry.type}`, {
            actor: entry.actor,
            subject: entry.subject ?? '',
        });

    const navigate = (params: {
        search?: string;
        type?: string;
        page?: number;
        sort?: string;
    }) => {
        router.get(
            index(tenant.slug).url,
            {
                filter: {
                    search: params.search ?? filters.search ?? undefined,
                    type:
                        (params.type ?? filters.type ?? AllTypes) === AllTypes
                            ? undefined
                            : (params.type ?? filters.type),
                },
                sort: params.sort ?? filters.sort ?? undefined,
                page: params.page,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    useEffect(() => {
        const timeout = setTimeout(() => {
            if (search !== (filters.search ?? '')) {
                navigate({ search });
            }
        }, 300);

        return () => clearTimeout(timeout);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const columns: ColumnDef<AuditEntry>[] = [
        {
            // Seul tri autorise par le controleur (`allowedSorts('created_at')`).
            id: 'created_at',
            accessorKey: 'at',
            header: t('audit.columns.date'),
            cell: ({ row }) => (
                <span className="whitespace-nowrap">
                    {row.original.at
                        ? formatDateTime(row.original.at, locale)
                        : ''}
                </span>
            ),
        },
        {
            header: t('audit.columns.type'),
            cell: ({ row }) => (
                <Badge variant="secondary">
                    {t(`audit.types.${row.original.type}`)}
                </Badge>
            ),
        },
        {
            header: t('audit.columns.message'),
            cell: ({ row }) => message(row.original),
        },
        {
            header: t('audit.columns.actor'),
            accessorKey: 'actor',
            enableSorting: false,
        },
        {
            header: t('audit.columns.ip'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.ip ?? ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('audit.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('audit.title')}
                    description={t('audit.description')}
                />

                <p className="text-muted-foreground text-sm">
                    {t('audit.retention')}
                </p>

                <div className="flex flex-wrap items-center gap-2">
                    <Input
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder={t('audit.filters.search_placeholder')}
                        className="max-w-xs"
                        data-test="audit-search"
                    />
                    <Select
                        value={filters.type ?? AllTypes}
                        onValueChange={(type) => navigate({ type })}
                    >
                        <SelectTrigger
                            className="w-60"
                            data-test="audit-type-filter"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={AllTypes}>
                                {t('audit.filters.type_all')}
                            </SelectItem>
                            {types.map((item) => (
                                <SelectItem key={item} value={item}>
                                    {t(`audit.types.${item}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <DataTable
                    columns={columns}
                    data={entries}
                    meta={meta}
                    onPageChange={(page) => navigate({ page })}
                    // Sans parametre, le serveur trie par date decroissante : l'en-tete le montre.
                    sorting={sortingFromParam(filters.sort ?? '-created_at')}
                    onSortingChange={(sorting) =>
                        navigate({ sort: sortingToParam(sorting) })
                    }
                    rowTestId="audit-row"
                    emptyState={
                        <>
                            <p className="font-medium">
                                {t('audit.empty.title')}
                            </p>
                            <p className="text-muted-foreground text-sm">
                                {t('audit.empty.description')}
                            </p>
                        </>
                    }
                />
            </div>
        </>
    );
}

Audit.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'audit.title'),
            href: index(props.tenant.slug),
        },
    ],
});
