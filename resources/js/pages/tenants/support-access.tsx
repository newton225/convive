import { Head } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { LifeBuoy, ShieldOff } from 'lucide-react';
import { ConsoleTable } from '@/components/console/console-table';
import { PendingActionButton } from '@/components/console/pending-action-button';
import Heading from '@/components/heading';
import { SampleBanner } from '@/components/sample-banner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { show } from '@/routes/tenants/support-access';
import type {
    ActiveSupportAccess,
    PastSupportAccess,
    SupportOperatorOption,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    isSample: boolean;
    durations: number[];
    operators: SupportOperatorOption[];
    activeAccess: ActiveSupportAccess | null;
    pastAccesses: PastSupportAccess[];
};

/**
 * README ecran 25, « acces du support » : le Proprietaire ouvre a une personne nommee de l'equipe
 * Convive un acces en lecture seule, de 24 heures au plus, le revoque, et relit ce qui a ete
 * consulte (README section 3, « Console d'exploitation »).
 */
export default function SupportAccess({
    isSample,
    durations,
    operators,
    activeAccess,
    pastAccesses,
}: Props) {
    const { t, locale } = useTranslation();

    const historyColumns: ColumnDef<PastSupportAccess>[] = [
        {
            header: t('support_access.history.columns.operator'),
            accessorKey: 'operator',
        },
        {
            header: t('support_access.history.columns.granted_at'),
            cell: ({ row }) => formatDateTime(row.original.grantedAt, locale),
        },
        {
            header: t('support_access.history.columns.ended_at'),
            cell: ({ row }) => formatDateTime(row.original.endedAt, locale),
        },
        {
            header: t('support_access.history.columns.end_reason'),
            cell: ({ row }) =>
                t(
                    `support_access.history.end_reasons.${row.original.endReason}`,
                ),
        },
        {
            header: t('support_access.history.columns.views'),
            accessorKey: 'viewsCount',
        },
    ];

    return (
        <>
            <Head title={t('support_access.title')} />

            <div className="flex flex-col space-y-6">
                {isSample && <SampleBanner />}

                <Heading
                    variant="small"
                    title={t('support_access.title')}
                    description={t('support_access.description')}
                />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('support_access.rules.title')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <ul className="text-muted-foreground list-disc space-y-1 pl-5 text-sm">
                            <li>{t('support_access.rules.read_only')}</li>
                            <li>{t('support_access.rules.limited')}</li>
                            <li>{t('support_access.rules.named')}</li>
                            <li>{t('support_access.rules.logged')}</li>
                            <li>{t('support_access.rules.banner')}</li>
                        </ul>
                    </CardContent>
                </Card>

                {activeAccess && (
                    <Card data-test="active-support-access">
                        <CardHeader>
                            <CardTitle>
                                {t('support_access.active.title')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm">
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {t('support_access.active.summary', {
                                        operator: activeAccess.operator,
                                        expires: formatDateTime(
                                            activeAccess.expiresAt,
                                            locale,
                                        ),
                                    })}
                                </p>
                                {activeAccess.grantedBy && (
                                    <p className="text-muted-foreground">
                                        {t('support_access.active.granted', {
                                            granted_by: activeAccess.grantedBy,
                                            date: formatDateTime(
                                                activeAccess.grantedAt,
                                                locale,
                                            ),
                                        })}
                                    </p>
                                )}
                            </div>

                            <PendingActionButton
                                icon={ShieldOff}
                                variant="destructive"
                                label={t('support_access.active.revoke')}
                            />

                            <div className="space-y-2">
                                <h3 className="font-medium">
                                    {t('support_access.active.views')}
                                </h3>
                                {activeAccess.views.length === 0 ? (
                                    <p className="text-muted-foreground">
                                        {t('support_access.active.views_empty')}
                                    </p>
                                ) : (
                                    <ul className="space-y-1">
                                        {activeAccess.views.map((view) => (
                                            <li key={view.at}>
                                                <span className="text-muted-foreground">
                                                    {formatDateTime(
                                                        view.at,
                                                        locale,
                                                    )}{' '}
                                                    :
                                                </span>{' '}
                                                {t(
                                                    `support_access.pages.${view.page}`,
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('support_access.grant.title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <p className="text-muted-foreground">
                            {activeAccess
                                ? t('support_access.grant.one_at_a_time')
                                : t('support_access.grant.none_active')}
                        </p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="support-operator">
                                    {t('support_access.grant.operator')}
                                </Label>
                                <Select disabled={activeAccess !== null}>
                                    <SelectTrigger id="support-operator">
                                        <SelectValue
                                            placeholder={t(
                                                'support_access.grant.operator_placeholder',
                                            )}
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {operators.map((operator) => (
                                            <SelectItem
                                                key={operator.id}
                                                value={String(operator.id)}
                                            >
                                                {operator.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="support-duration">
                                    {t('support_access.grant.duration')}
                                </Label>
                                <Select
                                    disabled={activeAccess !== null}
                                    defaultValue={String(
                                        durations[durations.length - 1],
                                    )}
                                >
                                    <SelectTrigger id="support-duration">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {durations.map((hours) => (
                                            <SelectItem
                                                key={hours}
                                                value={String(hours)}
                                            >
                                                {t(
                                                    'support_access.grant.hours',
                                                    {
                                                        count: hours,
                                                    },
                                                )}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <PendingActionButton
                            icon={LifeBuoy}
                            variant="default"
                            label={t('support_access.grant.submit')}
                        />
                    </CardContent>
                </Card>

                <section className="space-y-3">
                    <h3 className="font-medium">
                        {t('support_access.history.title')}
                    </h3>
                    <ConsoleTable
                        columns={historyColumns}
                        data={pastAccesses}
                        emptyState={
                            <p className="text-muted-foreground text-sm">
                                {t('support_access.history.empty')}
                            </p>
                        }
                    />
                </section>
            </div>
        </>
    );
}

SupportAccess.layout = (props: {
    tenant: { slug: string };
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'support_access.title'),
            href: show(props.tenant.slug),
        },
    ],
});
