import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { ExternalLink } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { WithdrawAnnouncementDialog } from '@/components/console/withdraw-announcement-dialog';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDate } from '@/lib/format-date';
import { showcase } from '@/routes/console';
import type {
    ConsoleAnnouncement,
    ConsoleWithdrawnAnnouncement,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    announcements: ConsoleAnnouncement[];
    withdrawn: ConsoleWithdrawnAnnouncement[];
};

/**
 * README ecran 32 : la moderation de la vitrine. Un retrait est toujours motive, et le motif est
 * transmis a l'organisation (README section 3).
 */
export default function Showcase({
    isSample,
    announcements,
    withdrawn,
}: Props) {
    const { t, locale } = useTranslation();

    const activeColumns: ColumnDef<ConsoleAnnouncement>[] = [
        {
            header: t('console.showcase.columns.event'),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.eventName}</span>
            ),
        },
        {
            header: t('console.showcase.columns.organisation'),
            accessorKey: 'organisationName',
        },
        {
            header: t('console.showcase.columns.announced_at'),
            cell: ({ row }) => formatDate(row.original.announcedAt, locale),
        },
        {
            header: t('console.showcase.columns.starts_at'),
            cell: ({ row }) =>
                row.original.startsAt
                    ? formatDate(row.original.startsAt, locale)
                    : '',
        },
        {
            id: 'actions',
            header: '',
            cell: ({ row }) => (
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <a
                        href={row.original.publicUrl}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-sm"
                    >
                        <ExternalLink className="size-4" />
                        {t('console.showcase.open_link')}
                    </a>
                    <WithdrawAnnouncementDialog
                        id={row.original.id}
                        eventName={row.original.eventName}
                    />
                </div>
            ),
        },
    ];

    const withdrawnColumns: ColumnDef<ConsoleWithdrawnAnnouncement>[] = [
        {
            header: t('console.showcase.columns.event'),
            accessorKey: 'eventName',
        },
        {
            header: t('console.showcase.columns.organisation'),
            accessorKey: 'organisationName',
        },
        {
            header: t('console.showcase.columns.withdrawn_at'),
            cell: ({ row }) => formatDate(row.original.withdrawnAt, locale),
        },
        {
            header: t('console.showcase.columns.actor'),
            cell: ({ row }) => row.original.actor ?? t('console.system_actor'),
        },
        {
            header: t('console.showcase.columns.reason'),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.reason}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('console.showcase.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('console.showcase.title')}
                    description={t('console.showcase.description')}
                />

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.showcase.active')}
                    </h3>
                    <ConsoleTable
                        columns={activeColumns}
                        data={announcements}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.showcase.active_empty')}
                            </p>
                        }
                    />
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.showcase.withdrawn')}
                    </h3>
                    <ConsoleTable
                        columns={withdrawnColumns}
                        data={withdrawn}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.showcase.withdrawn_empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

Showcase.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.showcase.title'),
            href: showcase(),
        },
    ],
});
