import { Head, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { LifeBuoy, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ConsoleTable } from '@/components/console/console-table';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
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
import { destroy, show, store } from '@/routes/tenants/support-access';
import type {
    ActiveSupportAccess,
    PastSupportAccess,
    SupportOperatorOption,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    durations: number[];
    operators: SupportOperatorOption[];
    activeAccess: ActiveSupportAccess | null;
    pastAccesses: PastSupportAccess[];
};

/**
 * README ecran 25, « acces du support » : le Proprietaire ouvre a une personne nommee de l'equipe
 * Convive un acces en lecture seule, de 24 heures au plus, le revoque, et relit ce qui a ete
 * consulte (README section 3, « Console d'exploitation »). Les regles sont appliquees par le
 * serveur (`ManageSupportAccess`, `EnsureTenantMembership`) ; l'ecran les reflete.
 */
export default function SupportAccess({
    tenant,
    durations,
    operators,
    activeAccess,
    pastAccesses,
}: Props) {
    const { t, locale } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [revoking, setRevoking] = useState(false);
    const form = useForm({
        operator_id: '',
        duration: String(durations[durations.length - 1]),
    });
    const chosenOperator = operators.find(
        (operator) => String(operator.id) === form.data.operator_id,
    );

    const open = () => {
        form.post(store(tenant.slug).url, {
            preserveScroll: true,
            onSuccess: () => form.reset('operator_id'),
            onFinish: () => setConfirming(false),
        });
    };

    const revoke = (grantId: number) => {
        router.delete(destroy([tenant.slug, grantId]).url, {
            preserveScroll: true,
            onStart: () => setRevoking(true),
            onFinish: () => setRevoking(false),
        });
    };

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

                            <SubmitButton
                                type="button"
                                variant="destructive"
                                processing={revoking}
                                onClick={() => revoke(activeAccess.id)}
                                data-test="support-access-revoke"
                            >
                                <ShieldOff />
                                {t('support_access.active.revoke')}
                            </SubmitButton>

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
                                            <li key={view.id}>
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
                                <Select
                                    disabled={activeAccess !== null}
                                    value={form.data.operator_id}
                                    onValueChange={(value) =>
                                        form.setData('operator_id', value)
                                    }
                                >
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
                                <InputError message={form.errors.operator_id} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="support-duration">
                                    {t('support_access.grant.duration')}
                                </Label>
                                <Select
                                    disabled={activeAccess !== null}
                                    value={form.data.duration}
                                    onValueChange={(value) =>
                                        form.setData('duration', value)
                                    }
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
                                <InputError message={form.errors.duration} />
                            </div>
                        </div>
                        <SubmitButton
                            type="button"
                            processing={form.processing}
                            disabled={
                                activeAccess !== null ||
                                chosenOperator === undefined
                            }
                            onClick={() => setConfirming(true)}
                            data-test="support-access-open"
                        >
                            <LifeBuoy />
                            {t('support_access.grant.submit')}
                        </SubmitButton>
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

            {/* Ouvrir son contenu a l'editeur engage toute l'organisation : la confirmation
                rappelle a qui, pour combien de temps, et ce que la personne pourra lire. */}
            <ConfirmActionDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={t('support_access.grant.confirm_title', {
                    operator: chosenOperator?.name ?? '',
                })}
                description={t('support_access.grant.confirm_description', {
                    duration: t('support_access.grant.hours', {
                        count: Number(form.data.duration),
                    }),
                })}
                confirmLabel={t('support_access.grant.submit')}
                onConfirm={open}
                processing={form.processing}
                testId="support-access-confirm"
            />
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
