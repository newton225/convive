import { Head, router } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ConsoleTable } from '@/components/console/console-table';
import { InviteOperatorDialog } from '@/components/console/invite-operator-dialog';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDate, formatRelative } from '@/lib/format-date';
import { team } from '@/routes/console';
import { destroy } from '@/routes/console/team';
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
 * README ecran 34 : l'equipe editeur. Double authentification obligatoire, trois profils
 * (Fondateur, Support, Comptabilite) qui ouvrent chacun leurs ecrans de la console. Un Fondateur
 * invite une personne par son adresse et la retire ; les Fondateurs de depart, definis dans la
 * configuration du serveur, ne se retirent pas d'ici.
 */
export default function Team({ isSample, operators, invitations }: Props) {
    const { t, locale } = useTranslation();
    // L'adresse a retirer (membre ou invitation), tant que la confirmation est ouverte.
    const [removing, setRemoving] = useState<{
        operatorId: number;
        email: string;
    } | null>(null);
    const [processing, setProcessing] = useState(false);

    const remove = () => {
        if (!removing) {
            return;
        }

        router.delete(destroy(removing.operatorId).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRemoving(null);
            },
        });
    };

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
        {
            id: 'actions',
            header: t('console.team.actions'),
            cell: ({ row }) => {
                const { operatorId, email, removable } = row.original;

                if (operatorId === null) {
                    return (
                        <span className="text-muted-foreground text-xs">
                            {t('console.team.bootstrap_founder')}
                        </span>
                    );
                }

                return removable ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setRemoving({ operatorId, email })}
                        data-test="console-team-remove"
                    >
                        {t('console.team.remove')}
                    </Button>
                ) : null;
            },
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
        {
            id: 'actions',
            header: t('console.team.actions'),
            cell: ({ row }) => (
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setRemoving({
                            operatorId: row.original.id,
                            email: row.original.email,
                        })
                    }
                    data-test="console-team-cancel-invitation"
                >
                    {t('console.team.cancel_invitation')}
                </Button>
            ),
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
                    <InviteOperatorDialog profiles={profiles} />
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

            <ConfirmActionDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={t('console.team.remove_confirm.title', {
                    email: removing?.email ?? '',
                })}
                description={t('console.team.remove_confirm.description')}
                confirmLabel={t('console.team.remove_confirm.confirm')}
                onConfirm={remove}
                processing={processing}
                destructive
                testId="console-team-remove-confirm"
            />
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
