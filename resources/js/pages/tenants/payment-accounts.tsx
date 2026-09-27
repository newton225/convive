import { Form, Head, router } from '@inertiajs/react';
import { AlertTriangle, Clock, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { SubmitButton } from '@/components/submit-button';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import { edit, index as tenantsIndex } from '@/routes/tenants';
import {
    approve,
    cancel,
    destroy,
    index,
    store,
    update,
} from '@/routes/tenants/payment-accounts';
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
};

export default function PaymentAccounts({
    tenant,
    accounts,
    channels,
    activationDelayHours,
}: Props) {
    const { t, locale } = useTranslation();
    const [deleting, setDeleting] = useState<PaymentAccount | null>(null);
    const [approving, setApproving] = useState<PaymentAccount | null>(null);
    const [approveProcessing, setApproveProcessing] = useState(false);

    const recentlyChanged = accounts.some((account) => account.changedRecently);

    return (
        <>
            <Head title={t('payment_accounts.title')} />

            <h1 className="sr-only">{t('payment_accounts.title')}</h1>

            <div className="flex flex-col space-y-6">
                <Heading
                    variant="small"
                    title={t('payment_accounts.title')}
                    description={t('payment_accounts.description', {
                        hours: activationDelayHours,
                    })}
                />

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
                                    <p>
                                        {t(
                                            'payment_accounts.pending.new_number',
                                            {
                                                value:
                                                    account.pending
                                                        .accountNumber ?? '',
                                            },
                                        )}
                                    </p>
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
                                            'payment_accounts.pending.activates_at',
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
                                                router.post(
                                                    cancel([
                                                        tenant.slug,
                                                        account.id,
                                                    ]).url,
                                                )
                                            }
                                        >
                                            {t(
                                                'payment_accounts.actions.cancel_change',
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            ) : null}

                            <AccountForm
                                action={update.form([tenant.slug, account.id])}
                                account={account}
                                channels={channels}
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
                        action={store.form(tenant.slug)}
                        account={null}
                        channels={channels}
                    />
                </div>
            </div>

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
            />

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

function AccountForm({
    action,
    account,
    channels,
}: {
    action: { action: string; method: 'post' | 'patch' };
    account: PaymentAccount | null;
    channels: PaymentChannelOption[];
}) {
    const { t } = useTranslation();
    const suffix = account?.id ?? 'new';

    return (
        <Form {...action} className="space-y-4">
            {({ errors, processing }) => (
                <>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor={`label-${suffix}`}>
                                {t('payment_accounts.fields.label')}
                            </Label>
                            <Input
                                id={`label-${suffix}`}
                                name="label"
                                data-test="payment-account-label"
                                defaultValue={account?.label ?? ''}
                                placeholder={t(
                                    'payment_accounts.fields.label_placeholder',
                                )}
                                required
                            />
                            <InputError message={errors.label} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`channel-${suffix}`}>
                                {t('payment_accounts.fields.channel')}
                            </Label>
                            <Select
                                name="channel"
                                defaultValue={account?.channel ?? undefined}
                            >
                                <SelectTrigger
                                    id={`channel-${suffix}`}
                                    className="w-full"
                                    data-test="payment-account-channel"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {channels.map((channel) => (
                                        <SelectItem
                                            key={channel.value}
                                            value={channel.value}
                                        >
                                            {channel.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.channel} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`number-${suffix}`}>
                                {t('payment_accounts.fields.account_number')}
                            </Label>
                            <Input
                                id={`number-${suffix}`}
                                name="account_number"
                                data-test="payment-account-number"
                                defaultValue={account?.accountNumber ?? ''}
                            />
                            <InputError message={errors.account_number} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor={`holder-${suffix}`}>
                                {t('payment_accounts.fields.holder_name')}
                            </Label>
                            <Input
                                id={`holder-${suffix}`}
                                name="holder_name"
                                defaultValue={account?.holderName ?? ''}
                            />
                            <InputError message={errors.holder_name} />
                        </div>

                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor={`instructions-${suffix}`}>
                                {t('payment_accounts.fields.instructions')}
                            </Label>
                            <Input
                                id={`instructions-${suffix}`}
                                name="instructions"
                                defaultValue={account?.instructions ?? ''}
                                placeholder={t(
                                    'payment_accounts.fields.instructions_placeholder',
                                )}
                            />
                            <InputError message={errors.instructions} />
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            name="is_active"
                            value="1"
                            defaultChecked={account?.isActive ?? true}
                        />
                        {t('payment_accounts.fields.is_active')}
                    </label>

                    <SubmitButton
                        data-test="payment-account-submit"
                        processing={processing}
                    >
                        {account ? null : <Plus />}
                        {account
                            ? t('payment_accounts.actions.save')
                            : t('payment_accounts.actions.create')}
                    </SubmitButton>
                </>
            )}
        </Form>
    );
}

PaymentAccounts.layout = (props: {
    tenant: Pick<Tenant, 'name' | 'slug'>;
    translations: Translations;
}) => ({
    breadcrumbs: [
        {
            title: translate(props.translations, 'tenants.index.title'),
            href: tenantsIndex(),
        },
        {
            title: props.tenant.name,
            href: edit(props.tenant.slug),
        },
        {
            title: translate(props.translations, 'payment_accounts.title'),
            href: index(props.tenant.slug),
        },
    ],
});
