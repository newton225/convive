import { Head, router, useForm } from '@inertiajs/react';
import type { ColumnDef } from '@tanstack/react-table';
import { LifeBuoy, ShieldOff } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ConsoleTable } from '@/components/console/console-table';
import { ExtendSupportAccessDialog } from '@/components/extend-support-access-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { SubmitButton } from '@/components/submit-button';
import { SupportRequestCard } from '@/components/support-request-card';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    PendingSupportRequest,
    SupportEventOption,
    SupportOperatorOption,
    Translations,
} from '@/types';

type Props = {
    tenant: { slug: string; name: string };
    durations: number[];
    maxHours: number;
    events: SupportEventOption[];
    operators: SupportOperatorOption[];
    activeAccess: ActiveSupportAccess | null;
    pendingRequest: PendingSupportRequest | null;
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
    maxHours,
    events,
    operators,
    activeAccess,
    pendingRequest,
    pastAccesses,
}: Props) {
    const { t, locale } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    const [revoking, setRevoking] = useState(false);
    // La demande d'aide deja ecrite n'est pas a ressaisir : son motif et la personne qui l'a
    // prise en charge sont repris, il ne reste qu'a choisir la duree.
    const takenOperator = operators.find(
        (operator) => operator.id === pendingRequest?.takenById,
    );
    const form = useForm({
        operator_id: takenOperator ? String(takenOperator.id) : '',
        duration: String(durations[durations.length - 1]),
        // Vide : toute l'organisation.
        event_id: '',
        reason: pendingRequest?.reason ?? '',
    });
    const chosenEvent = events.find(
        (event) => String(event.id) === form.data.event_id,
    );
    const chosenOperator = operators.find(
        (operator) => String(operator.id) === form.data.operator_id,
    );

    const open = () => {
        form.transform((data) => ({
            ...data,
            event_id: data.event_id === '' ? null : data.event_id,
        }));

        form.post(store(tenant.slug).url, {
            preserveScroll: true,
            onSuccess: () => form.reset('operator_id', 'event_id', 'reason'),
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
            header: t('support_access.history.columns.reason'),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.reason ?? ''}
                </span>
            ),
        },
        {
            header: t('support_access.history.columns.scope'),
            cell: ({ row }) =>
                row.original.event ?? t('support_access.grant.scope_all'),
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
        {
            header: t('support_access.history.columns.closing_note'),
            cell: ({ row }) => (
                <span className="text-muted-foreground">
                    {row.original.closingNote ?? ''}
                </span>
            ),
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
                            <li>
                                {t('support_access.rules.limited', {
                                    count: maxHours,
                                })}
                            </li>
                            <li>{t('support_access.rules.scope')}</li>
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

                            <p data-test="support-access-scope">
                                {activeAccess.event
                                    ? t('support_access.active.scope_event', {
                                          event: activeAccess.event,
                                      })
                                    : t('support_access.active.scope_all')}
                            </p>

                            {activeAccess.reason ? (
                                <p>
                                    <span className="text-muted-foreground">
                                        {t('support_access.active.reason')} :
                                    </span>{' '}
                                    {activeAccess.reason}
                                </p>
                            ) : null}

                            <div className="flex flex-wrap gap-2">
                                <ExtendSupportAccessDialog
                                    tenantSlug={tenant.slug}
                                    grantId={activeAccess.id}
                                    operator={activeAccess.operator}
                                    durations={durations}
                                    maxHours={maxHours}
                                />
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
                            </div>

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

                {/* Personne de visible, ou une demande deja partie : l'organisation previent
                    l'equipe Convive, et suit ou en est sa demande. */}
                {activeAccess === null &&
                (pendingRequest !== null || operators.length === 0) ? (
                    <SupportRequestCard
                        tenantSlug={tenant.slug}
                        pendingRequest={pendingRequest}
                    />
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('support_access.grant.title')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4 text-sm">
                        <p className="text-muted-foreground">
                            {activeAccess
                                ? t('support_access.grant.one_at_a_time')
                                : operators.length === 0
                                  ? t('support_access.grant.no_operator')
                                  : t('support_access.grant.none_active')}
                        </p>
                        <div className="grid items-start gap-4 sm:grid-cols-2">
                            <RequiredFieldsNote />
                            <div className="grid gap-2">
                                <Label htmlFor="support-operator" required>
                                    {t('support_access.grant.operator')}
                                </Label>
                                <Select
                                    disabled={
                                        activeAccess !== null ||
                                        operators.length === 0
                                    }
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
                                <Label htmlFor="support-duration" required>
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
                        <div className="grid gap-2">
                            <Label htmlFor="support-scope">
                                {t('support_access.grant.scope')}
                            </Label>
                            <Select
                                disabled={activeAccess !== null}
                                value={form.data.event_id || 'all'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'event_id',
                                        value === 'all' ? '' : value,
                                    )
                                }
                            >
                                <SelectTrigger id="support-scope">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        {t('support_access.grant.scope_all')}
                                    </SelectItem>
                                    {events.map((event) => (
                                        <SelectItem
                                            key={event.id}
                                            value={String(event.id)}
                                        >
                                            {t(
                                                'support_access.grant.scope_event',
                                                { event: event.name },
                                            )}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="text-muted-foreground text-xs">
                                {t('support_access.grant.scope_hint')}
                            </p>
                            <InputError message={form.errors.event_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="support-reason" required>
                                {t('support_access.grant.reason')}
                            </Label>
                            <Textarea
                                id="support-reason"
                                name="reason"
                                rows={3}
                                maxLength={500}
                                disabled={activeAccess !== null}
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                            <p className="text-muted-foreground text-xs">
                                {t('support_access.grant.reason_hint')}
                            </p>
                            <InputError message={form.errors.reason} />
                        </div>
                        <SubmitButton
                            type="button"
                            processing={form.processing}
                            disabled={
                                activeAccess !== null ||
                                chosenOperator === undefined ||
                                form.data.reason.trim() === ''
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
                description={t(
                    chosenEvent
                        ? 'support_access.grant.confirm_description_event'
                        : 'support_access.grant.confirm_description',
                    {
                        event: chosenEvent?.name ?? '',
                        duration: t('support_access.grant.hours', {
                            count: Number(form.data.duration),
                        }),
                    },
                )}
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
