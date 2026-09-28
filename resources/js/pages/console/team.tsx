import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { UserPlus } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { PendingActionButton } from '@/components/console/pending-action-button';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDate, formatRelative } from '@/lib/format-date';
import { team } from '@/routes/console';
import type {
    ConsoleOperator,
    ConsoleOperatorInvitation,
    ConsoleOperatorProfile,
    Translations,
} from '@/types';

type Props = {
    isSample: boolean;
    operators: ConsoleOperator[];
    invitations: ConsoleOperatorInvitation[];
};

const profiles: ConsoleOperatorProfile[] = ['founder', 'support', 'accounting'];

/**
 * README ecran 34 : l'equipe editeur. Comptes distincts des comptes d'organisation, double
 * authentification obligatoire, trois profils (Fondateur, Support, Comptabilite).
 */
export default function Team({ isSample, operators, invitations }: Props) {
    const { t, locale } = useTranslation();

    const operatorColumns: ColumnDef<ConsoleOperator>[] = [
        {
            header: t('console.team.columns.name'),
            cell: ({ row }) => (
                <span className="font-medium">{row.original.name}</span>
            ),
        },
        {
            header: t('console.team.columns.email'),
            accessorKey: 'email',
        },
        {
            header: t('console.team.columns.profile'),
            cell: ({ row }) => (
                <Badge variant="secondary">
                    {t(`console.team.profiles.${row.original.profile}`)}
                </Badge>
            ),
        },
        {
            header: t('console.team.columns.two_factor'),
            cell: ({ row }) =>
                row.original.twoFactor ? (
                    t('console.team.two_factor_on')
                ) : (
                    <Badge variant="destructive">
                        {t('console.team.two_factor_off')}
                    </Badge>
                ),
        },
        {
            header: t('console.team.columns.last_login'),
            cell: ({ row }) =>
                row.original.lastLoginAt
                    ? formatRelative(row.original.lastLoginAt, locale)
                    : '',
        },
    ];

    const invitationColumns: ColumnDef<ConsoleOperatorInvitation>[] = [
        {
            header: t('console.team.columns.email'),
            accessorKey: 'email',
        },
        {
            header: t('console.team.columns.profile'),
            cell: ({ row }) =>
                t(`console.team.profiles.${row.original.profile}`),
        },
        {
            header: t('console.team.columns.sent_at'),
            cell: ({ row }) => formatDate(row.original.sentAt, locale),
        },
    ];

    return (
        <>
            <Head title={t('console.team.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <div className="flex flex-wrap items-start justify-between gap-3">
                    <Heading
                        variant="small"
                        title={t('console.team.title')}
                        description={t('console.team.description')}
                    />
                    <PendingActionButton
                        icon={UserPlus}
                        variant="default"
                        label={t('console.team.invite')}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    {profiles.map((profile) => (
                        <Card key={profile}>
                            <CardHeader>
                                <CardTitle>
                                    {t(`console.team.profiles.${profile}`)}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="text-muted-foreground text-sm">
                                {t(
                                    `console.team.profile_descriptions.${profile}`,
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.team.operators')}
                    </h3>
                    <ConsoleTable
                        columns={operatorColumns}
                        data={operators}
                        emptyState={null}
                    />
                </section>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('console.team.invitations')}
                    </h3>
                    <ConsoleTable
                        columns={invitationColumns}
                        data={invitations}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('console.team.invitations_empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

Team.layout = (props: { translations: Translations }) => ({
    wide: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'console.team.title'),
            href: team(),
        },
    ],
});
