import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Clock, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ConfirmSummary } from '@/components/confirm-summary';
import { AccountForm } from '@/components/payment-accounts/account-form';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { TwoFactorReconfirmDialog } from '@/components/two-factor-reconfirm-dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useGettingStartedReturn } from '@/hooks/use-getting-started-return';
import { translate, useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import {
    approve,
    cancel,
    destroy,
    index,
    store,
    update,
} from '@/routes/tenants/payment-accounts';
import { edit as securityEdit } from '@/routes/security';
import type {
    PaymentAccount,
    PaymentChannelOption,
    Tenant,
    Translations,
} from '@/types';

type Props = {
    tenant: Pick<Tenant, 'id' | 'name' | 'slug'>;
    accounts: PaymentAccount[];
    channels: PaymentChannelOption[];
    activationDelayHours: number;
    delayActive: boolean;
    twoFactorEnabled: boolean;
};

export default function PaymentAccounts({
    tenant,
    accounts,
    channels,
    activationDelayHours,
    delayActive,
    twoFactorEnabled,
}: Props) {
    const { t, locale } = useTranslation();
    const gettingStartedReturn = useGettingStartedReturn();
    const [deleting, setDeleting] = useState<PaymentAccount | null>(null);
    const [approving, setApproving] = useState<PaymentAccount | null>(null);
    const [approveProcessing, setApproveProcessing] = useState(false);
    const [cancelling, setCancelling] = useState<PaymentAccount | null>(null);
    const [cancelProcessing, setCancelProcessing] = useState(false);

    const recentlyChanged = accounts.some((account) => account.changedRecently);

    // Le bouton retour du navigateur reaffiche la page telle qu'elle etait, sans la redemander : apres
    // avoir active la double authentification, l'encadre qui la reclame resterait affiche a tort.
    useEffect(() => {
        if (!twoFactorEnabled) {
            router.reload({ only: ['twoFactorEnabled'] });
        }
    }, [twoFactorEnabled]);

    return (
        <>
            <Head title={t('payment_accounts.title')} />

            <h1 className="sr-only">{t('payment_accounts.title')}</h1>

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('payment_accounts.title')}
                    description={t(
                        delayActive
                            ? 'payment_accounts.description'
                            : 'payment_accounts.description_before_publication',
                        { hours: activationDelayHours },
                    )}
                />

                {twoFactorEnabled ? null : (
                    <Alert data-test="payment-account-two-factor-required">
                        <AlertTriangle />
                        <AlertDescription>
                            <p>
                                {t(
                                    'account.two_factor_reconfirm.setup_required',
                                )}
                            </p>
                            <Link
                                href={securityEdit({
                                    query: {
                                        for: 'payment-accounts',
                                        tenant: tenant.slug,
                                    },
                                })}
                                className="font-medium underline underline-offset-4"
                            >
                                {t('account.two_factor_reconfirm.setup_link')}
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                {recentlyChanged ? (
                    <p
                        className="flex items-start gap-2 rounded-lg border p-3 text-sm"
                        data-test="payment-account-recent-notice"
                    >
                        <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
                        {t('payment_accounts.notice.recent_change', {
                            days: 7,
                        })}
                    </p>
                ) : null}

                <div className="space-y-4">
                    {accounts.map((account) => (
                        <div
                            key={account.id}
                            data-test="payment-account-row"
                            className="space-y-4 rounded-lg border p-4"
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {account.label}
                                </span>
                                <Badge
                                    variant={
                                        account.isPubliclyVisible
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {account.isPubliclyVisible
                                        ? t('payment_accounts.badges.visible')
                                        : t('payment_accounts.badges.hidden')}
                                </Badge>
                                {account.pending ? (
                                    <Badge variant="secondary">
                                        <Clock className="h-3 w-3" />
                                        {t('payment_accounts.badges.pending')}
                                    </Badge>
                                ) : null}
                            </div>

                            {account.pending ? (
                                <div
                                    className="bg-muted space-y-1 rounded-md p-3 text-sm"
                                    data-test="payment-account-pending"
                                >
                                    <p className="font-medium">
                                        {t('payment_accounts.pending.title')}
                                    </p>
                                    {/* Toute la demande, pas seulement le numero : les champs du
                                        formulaire montrent les valeurs actives, vides pour un
                                        compte neuf. */}
                                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-0.5">
                                        <dt className="text-muted-foreground">
                                            {t(
                                                'payment_accounts.fields.channel',
                                            )}
                                        </dt>
                                        <dd>
                                            {account.pending.channelLabel ?? ''}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            {t(
                                                'payment_accounts.fields.account_number',
                                            )}
                                        </dt>
                                        <dd>
                                            {account.pending.accountNumber ??
                                                t(
                                                    'payment_accounts.pending.none',
                                                )}
                                        </dd>
                                        <dt className="text-muted-foreground">
                                            {t(
                                                'payment_accounts.fields.holder_name',
                                            )}
                                        </dt>
                                        <dd>
                                            {account.pending.holderName ??
                                                t(
                                                    'payment_accounts.pending.none',
                                                )}
                                        </dd>
                                    </dl>
                                    <p className="text-muted-foreground">
                                        {t(
                                            'payment_accounts.pending.requested_by',
                                            {
                                                name:
                                                    account.pending
                                                        .requestedBy ?? '',
                                            },
                                        )}{' '}
                                        {t(
                                            account.neverActive
                                                ? 'payment_accounts.pending.activates_at_new'
                                                : 'payment_accounts.pending.activates_at',
                                            {
                                                date: formatDateTime(
                                                    account.pending
                                                        .activatesAt ?? '',
                                                    locale,
                                                ),
                                            },
                                        )}
                                    </p>

                                    <div className="flex flex-wrap gap-2 pt-2">
                                        {account.pending.mayApprove ? (
                                            <Button
                                                size="sm"
                                                data-test="payment-account-approve"
                                                onClick={() =>
                                                    setApproving(account)
                                                }
                                            >
                                                {t(
                                                    'payment_accounts.actions.approve',
                                                )}
                                            </Button>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">
                                                {t(
                                                    'payment_accounts.pending.not_the_requester',
                                                )}
                                            </span>
                                        )}

                                        <Button
                                            size="sm"
                                            variant="secondary"
                                            data-test="payment-account-cancel"
                                            onClick={() =>
                                                setCancelling(account)
                                            }
                                        >
                                            {t(
                                                account.neverActive
                                                    ? 'payment_accounts.actions.cancel_creation'
                                                    : 'payment_accounts.actions.cancel_change',
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            <AccountForm
                                action={update.form([tenant.slug, account.id])}
                                account={account}
                                channels={channels}
                                delayActive={delayActive}
                            />

                            <Button
                                variant="ghost"
                                size="sm"
                                data-test="payment-account-delete"
                                onClick={() => setDeleting(account)}
                            >
                                <Trash2 className="h-4 w-4" />
                                {t('payment_accounts.actions.delete')}
                            </Button>
                        </div>
                    ))}
                </div>

                <div className="space-y-4 rounded-lg border border-dashed p-4">
                    <Heading
                        variant="small"
                        title={t('payment_accounts.actions.create')}
                        description=""
                    />

                    <AccountForm
                        action={store.form(tenant.slug, gettingStartedReturn)}
                        account={null}
                        channels={channels}
                        delayActive={delayActive}
                    />
                </div>
            </div>

            <TwoFactorReconfirmDialog />

            <ConfirmActionDialog
                open={cancelling !== null}
                onOpenChange={(open) => !open && setCancelling(null)}
                title={t(
                    cancelling?.neverActive
                        ? 'payment_accounts.confirm_cancel.creation_title'
                        : 'payment_accounts.confirm_cancel.change_title',
                )}
                description={t(
                    cancelling?.neverActive
                        ? 'payment_accounts.confirm_cancel.creation_description'
                        : 'payment_accounts.confirm_cancel.change_description',
                    {
                        label: cancelling?.label ?? '',
                        number: cancelling?.pending?.accountNumber ?? '',
                    },
                )}
                confirmLabel={t(
                    cancelling?.neverActive
                        ? 'payment_accounts.actions.cancel_creation'
                        : 'payment_accounts.actions.cancel_change',
                )}
                destructive
                processing={cancelProcessing}
                testId="payment-account-cancel-confirm"
                onConfirm={() => {
                    if (cancelling) {
                        router.post(
                            cancel([tenant.slug, cancelling.id]).url,
                            {},
                            {
                                onStart: () => setCancelProcessing(true),
                                onFinish: () => {
                                    setCancelProcessing(false);
                                    setCancelling(null);
                                },
                            },
                        );
                    }
                }}
            />

            <ConfirmActionDialog
                open={approving !== null}
                onOpenChange={(open) => !open && setApproving(null)}
                title={t('payment_accounts.confirm_approve.title')}
                description={t('payment_accounts.confirm_approve.description', {
                    label: approving?.label ?? '',
                })}
                confirmLabel={t('payment_accounts.actions.approve')}
                processing={approveProcessing}
                testId="payment-account-approve-confirm"
                onConfirm={() => {
                    if (approving) {
                        router.post(
                            approve([tenant.slug, approving.id]).url,
                            {},
                            {
                                onStart: () => setApproveProcessing(true),
                                onFinish: () => setApproveProcessing(false),
                                onSuccess: () => setApproving(null),
                            },
                        );
                    }
                }}
            >
                {approving?.pending ? (
                    <ConfirmSummary
                        testId="payment-account-approve-summary"
                        items={[
                            {
                                label: t('payment_accounts.fields.label'),
                                value: approving.label,
                                emphasis: true,
                            },
                            {
                                label: t('payment_accounts.confirm.current'),
                                value: accountLine(
                                    approving.channelLabel,
                                    approving.accountNumber,
                                    approving.holderName,
                                ),
                                mono: true,
                            },
                            {
                                // Ce qui s'affichera aux invites des la confirmation : a comparer
                                // avec ce que la personne a annonce par un autre canal.
                                label: t('payment_accounts.confirm.new'),
                                value: accountLine(
                                    approving.pending.channelLabel,
                                    approving.pending.accountNumber,
                                    approving.pending.holderName,
                                ),
                                mono: true,
                                warning: true,
                            },
                            {
                                label: t(
                                    'payment_accounts.confirm.requested_by',
                                ),
                                value: approving.pending.requestedBy,
                                hidden: approving.pending.requestedBy === null,
                            },
                            {
                                label: t(
                                    'payment_accounts.confirm.activates_at',
                                ),
                                value: approving.pending.activatesAt
                                    ? formatDateTime(
                                          approving.pending.activatesAt,
                                          locale,
                                      )
                                    : null,
                                hidden: approving.pending.activatesAt === null,
                            },
                        ]}
                    />
                ) : null}
            </ConfirmActionDialog>

            <Dialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('payment_accounts.confirm_delete.title')}
                        </DialogTitle>
                        <DialogDescription>
                            {t('payment_accounts.confirm_delete.description', {
                                name: deleting?.label ?? '',
                            })}
                        </DialogDescription>
                    </DialogHeader>

                    {deleting ? (
                        <ConfirmSummary
                            testId="payment-account-delete-summary"
                            items={[
                                {
                                    label: t('payment_accounts.fields.label'),
                                    value: deleting.label,
                                    emphasis: true,
                                },
                                {
                                    label: t(
                                        'payment_accounts.confirm.current',
                                    ),
                                    value: accountLine(
                                        deleting.channelLabel,
                                        deleting.accountNumber,
                                        deleting.holderName,
                                    ),
                                    mono: true,
                                },
                                {
                                    label: t(
                                        'payment_accounts.confirm.visibility',
                                    ),
                                    value: t(
                                        deleting.isPubliclyVisible
                                            ? 'payment_accounts.badges.visible'
                                            : 'payment_accounts.badges.hidden',
                                    ),
                                    warning: deleting.isPubliclyVisible,
                                },
                            ]}
                        />
                    ) : null}

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">
                                {t('common.actions.cancel')}
                            </Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            data-test="payment-account-delete-confirm"
                            onClick={() => {
                                if (deleting) {
                                    router.delete(
                                        destroy([tenant.slug, deleting.id]).url,
                                        { onSuccess: () => setDeleting(null) },
                                    );
                                }
                            }}
                        >
                            {t('payment_accounts.actions.delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

// Canal, numero et titulaire sur une ligne, dans l'ordre ou l'invite les lit au moment de verser.
function accountLine(
    channel: string | null,
    number: string | null,
    holder: string | null,
): string {
    return [channel, number, holder].filter(Boolean).join(' · ') || '-';
}

PaymentAccounts.layout = (props: {
    tenant: Pick<Tenant, 'name' | 'slug'>;
    translations: Translations;
}) => ({
    narrow: true,
    breadcrumbs: [
        {
            title: translate(props.translations, 'payment_accounts.title'),
            href: index(props.tenant.slug),
        },
    ],
});
