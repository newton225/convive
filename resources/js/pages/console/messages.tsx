import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { TriangleAlert } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { messages as messagesRoute } from '@/routes/console';
import type {
    ConsoleMessage,
    ConsoleMessageChannel,
    ConsoleMessageType,
    Translations,
} from '@/types';

type Props = {
    channels: ConsoleMessageChannel[];
    types: ConsoleMessageType[];
    messages: ConsoleMessage[];
};

/**
 * L'ecran « Envois » de la console (README section 3) : combien de courriels et de messages
 * WhatsApp sont partis, de quel type, et les derniers. Un canal qui n'envoie pas encore reellement
 * est signale : ses messages sont comptes, mais personne ne les recoit.
 */
export default function ConsoleMessages({ channels, types, messages }: Props) {
    const { t, locale } = useTranslation();

    const columns: ColumnDef<ConsoleMessage>[] = [
        {
            header: t('console.messages.columns.date'),
            cell: ({ row }) => formatDateTime(row.original.at, locale),
        },
        {
            header: t('console.messages.columns.channel'),
            cell: ({ row }) => (
                <span className="flex flex-wrap items-center gap-2">
                    {t(`console.messages.channels.${row.original.channel}`)}
                    {row.original.simulated ? (
                        <Badge variant="outline">
                            {t('console.messages.simulated_badge')}
                        </Badge>
                    ) : null}
                </span>
            ),
        },
        {
            header: t('console.messages.columns.type'),
            accessorKey: 'type',
        },
        {
            header: t('console.messages.columns.organisation'),
            cell: ({ row }) => row.original.organisation ?? '',
        },
        {
            header: t('console.messages.columns.recipient'),
            cell: ({ row }) => (
                <span className="font-mono text-xs">
                    {row.original.recipient ?? ''}
                </span>
            ),
        },
    ];

    return (
        <>
            <Head title={t('console.messages.title')} />

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('console.messages.title')}
                    description={t('console.messages.description')}
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    {channels.map((channel) => (
                        <Card
                            key={channel.channel}
                            data-test={`console-messages-${channel.channel}`}
                        >
                            <CardHeader>
                                <CardTitle>
                                    {t(
                                        `console.messages.channels.${channel.channel}`,
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3 text-sm">
                                <p>
                                    {t('console.messages.counts', {
                                        day: channel.lastDay,
                                        week: channel.lastWeek,
                                    })}
                                </p>
                                {channel.simulated ? (
                                    <p className="flex gap-2">
                                        <TriangleAlert
                                            className="mt-0.5 size-4 shrink-0"
                                            aria-hidden
                                        />
                                        <span>
                                            {t(
                                                `console.messages.simulated.${channel.channel}`,
                                            )}
                                        </span>
                                    </p>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {types.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {t('console.messages.by_type')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="divide-y text-sm">
                                {types.map((type) => (
                                    <li
                                        key={type.type}
                                        className="flex items-center justify-between gap-3 py-2"
                                    >
                                        <span>{type.label}</span>
                                        <span className="tabular-nums">
                                            {type.count}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                ) : null}

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.messages.latest')}
                    </h3>
                    <p className="text-muted-foreground text-sm">
                        {t('console.messages.latest_hint')}
                    </p>
                    <ConsoleTable
                        columns={columns}
                        data={messages}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.messages.empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

ConsoleMessages.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.messages.title'),
            href: messagesRoute(),
        },
    ],
});
