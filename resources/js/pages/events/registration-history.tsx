import { Head, Link } from '@inertiajs/react';
import Heading from '@/components/heading';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { index as eventsIndex } from '@/routes/tenants/events';
import { index as registrationsIndex } from '@/routes/tenants/events/registrations';
import type { RegistrationHistoryEntry, Translations } from '@/types';

type Props = {
    tenant: { slug: string };
    event: { id: number; name: string };
    registration: {
        id: number;
        reference: string | null;
        name: string;
        statusLabel: string;
    };
    timeline: RegistrationHistoryEntry[];
    showsIp: boolean;
};

const kindTones = {
    guest: 'info',
    system: 'neutral',
    team: 'success',
} as const;

/**
 * L'historique d'un dossier : ce qui s'est passe, dans l'ordre, et qui en est l'auteur. Les faits de
 * l'invite et du systeme viennent du journal du dossier, les gestes de l'equipe du journal d'audit.
 */
export default function RegistrationHistory({
    event,
    registration,
    timeline,
    showsIp,
    tenant,
}: Props) {
    const { t, locale } = useTranslation();

    return (
        <>
            <Head title={t('registrations.history.title')} />

            <div className="flex flex-col space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Heading
                        variant="small"
                        title={t('registrations.history.title')}
                        description={`${registration.reference ?? ''} ${registration.name} (${registration.statusLabel})`.trim()}
                    />
                    <Link
                        href={registrationsIndex([tenant.slug, event.id])}
                        className="text-sm underline"
                        data-test="registration-history-back"
                    >
                        {t('registrations.history.back')}
                    </Link>
                </div>

                <p className="text-muted-foreground text-sm">
                    {t('registrations.history.description')}
                </p>

                {timeline.length === 0 ? (
                    <p
                        className="text-muted-foreground text-sm"
                        data-test="registration-history-empty"
                    >
                        {t('registrations.history.empty')}
                    </p>
                ) : (
                    <Table data-test="registration-history">
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    {t('registrations.history.columns.date')}
                                </TableHead>
                                <TableHead>
                                    {t('registrations.history.columns.what')}
                                </TableHead>
                                <TableHead>
                                    {t('registrations.history.columns.actor')}
                                </TableHead>
                                {showsIp ? (
                                    <TableHead>
                                        {t('registrations.history.columns.ip')}
                                    </TableHead>
                                ) : null}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {timeline.map((entry) => (
                                <TableRow key={entry.key}>
                                    <TableCell className="whitespace-nowrap">
                                        {entry.at
                                            ? formatDateTime(entry.at, locale)
                                            : ''}
                                    </TableCell>
                                    <TableCell>
                                        <div>{entry.label}</div>
                                        {entry.detail ? (
                                            <div className="text-muted-foreground text-xs">
                                                {entry.detail}
                                            </div>
                                        ) : null}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <StatusBadge
                                                tone={kindTones[entry.kind]}
                                            >
                                                {t(
                                                    `registrations.history.kinds.${entry.kind}`,
                                                )}
                                            </StatusBadge>
                                            <span>{entry.actor}</span>
                                        </div>
                                    </TableCell>
                                    {showsIp ? (
                                        <TableCell className="text-muted-foreground">
                                            {entry.ip ?? ''}
                                        </TableCell>
                                    ) : null}
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}

                <p className="text-muted-foreground text-xs">
                    {t('registrations.history.retention')}
                </p>
            </div>
        </>
    );
}

RegistrationHistory.layout = (props: {
    tenant: { slug: string };
    event: { id: number };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'events.title'),
            href: eventsIndex(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'registrations.title'),
            href: registrationsIndex([props.tenant.slug, props.event.id]),
        },
        {
            title: translate(props.translations, 'registrations.history.title'),
            href: registrationsIndex([props.tenant.slug, props.event.id]),
        },
    ],
});
