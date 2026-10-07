import { Form } from '@inertiajs/react';
import type { FormComponentRef } from '@inertiajs/core';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { ConfirmSummary } from '@/components/confirm-summary';
import InputError from '@/components/input-error';
import { LabelWithHelp } from '@/components/label-with-help';
import { RequiredFieldsNote } from '@/components/required-fields-note';
import { SubmitButton } from '@/components/submit-button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { PaymentAccount, PaymentChannelOption } from '@/types';

type AccountFormData = {
    label?: string;
    channel?: string;
    account_number?: string;
    holder_name?: string;
    instructions?: string;
    is_active?: string;
    // Ajoute a l'envoi par `transform`, une fois l'apercu confirme.
    confirmed?: number;
};

type Preview = {
    channel: string;
    accountNumber: string;
    holderName: string;
};

type Props = {
    action: { action: string; method: 'post' | 'patch' };
    account: PaymentAccount | null;
    channels: PaymentChannelOption[];
    // Faux tant que l'organisation n'a rien publie : le changement s'applique alors tout de suite,
    // apres un apercu confirme (decision du 2026-10-07).
    delayActive: boolean;
};

/**
 * Le formulaire d'un compte de versement, a la creation comme a la modification.
 */
export function AccountForm({ action, account, channels, delayActive }: Props) {
    const { t } = useTranslation();
    const suffix = account?.id ?? 'new';
    // Une demande en attente se relit et se corrige ici : le formulaire la reprend, et non les
    // coordonnees actives que les invites voient encore pendant le delai.
    const requested = account?.pending ?? null;
    const channelValue = requested ? requested.channel : account?.channel;
    const numberValue = requested
        ? requested.accountNumber
        : account?.accountNumber;
    const holderValue = requested ? requested.holderName : account?.holderName;
    // Le canal choisi dit si le numero est exige : tout canal sauf les especes designe un compte.
    const [channel, setChannel] = useState(channelValue ?? '');
    const requiresNumber =
        channels.find((option) => option.value === channel)?.hasAccountNumber ??
        true;

    const formRef = useRef<FormComponentRef<AccountFormData>>(null);
    const confirmed = useRef(false);
    const [preview, setPreview] = useState<Preview | null>(null);

    // Avant toute publication, le canal, le numero et le titulaire s'appliquent des l'envoi : on les
    // fait relire avant de partir. Le libelle et la consigne n'ont pas besoin de cet apercu.
    const previewOf = (data: AccountFormData): Preview | null => {
        const next = {
            channel: data.channel ?? '',
            accountNumber: data.account_number ?? '',
            holderName: data.holder_name ?? '',
        };
        const unchanged =
            account !== null &&
            next.channel === (account.channel ?? '') &&
            next.accountNumber === (account.accountNumber ?? '') &&
            next.holderName === (account.holderName ?? '');

        return delayActive || unchanged ? null : next;
    };

    return (
        <>
            <Form
                {...action}
                ref={formRef}
                setDefaultsOnSuccess
                className="space-y-4"
                transform={(data) =>
                    confirmed.current ? { ...data, confirmed: 1 } : data
                }
                onBefore={() => {
                    const data = formRef.current?.getData();
                    const next =
                        confirmed.current || !data ? null : previewOf(data);

                    if (next) {
                        setPreview(next);

                        return false;
                    }
                }}
                onFinish={() => {
                    confirmed.current = false;
                }}
            >
                {({ errors, processing, isDirty }) => (
                    <>
                        <RequiredFieldsNote />
                        {requested ? (
                            <p
                                className="text-muted-foreground text-sm"
                                data-test="payment-account-pending-hint"
                            >
                                {t('payment_accounts.form.pending_hint')}
                            </p>
                        ) : null}
                        <div className="grid items-start gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <LabelWithHelp
                                    htmlFor={`label-${suffix}`}
                                    label={t('payment_accounts.fields.label')}
                                    help={t('payment_accounts.help.label')}
                                    required
                                />
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
                                <LabelWithHelp
                                    htmlFor={`channel-${suffix}`}
                                    label={t('payment_accounts.fields.channel')}
                                    help={t('payment_accounts.help.channel')}
                                    required
                                />
                                <Select
                                    name="channel"
                                    defaultValue={channelValue ?? undefined}
                                    onValueChange={setChannel}
                                >
                                    <SelectTrigger
                                        id={`channel-${suffix}`}
                                        className="w-full"
                                        data-test="payment-account-channel"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {channels.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.channel} />
                            </div>

                            <div className="grid gap-2">
                                <LabelWithHelp
                                    htmlFor={`number-${suffix}`}
                                    label={t(
                                        'payment_accounts.fields.account_number',
                                    )}
                                    help={t(
                                        'payment_accounts.help.account_number',
                                    )}
                                    required={requiresNumber}
                                />
                                <Input
                                    id={`number-${suffix}`}
                                    name="account_number"
                                    data-test="payment-account-number"
                                    defaultValue={numberValue ?? ''}
                                />
                                <InputError message={errors.account_number} />
                            </div>

                            <div className="grid gap-2">
                                <LabelWithHelp
                                    htmlFor={`holder-${suffix}`}
                                    label={t(
                                        'payment_accounts.fields.holder_name',
                                    )}
                                    help={t(
                                        'payment_accounts.help.holder_name',
                                    )}
                                />
                                <Input
                                    id={`holder-${suffix}`}
                                    name="holder_name"
                                    defaultValue={holderValue ?? ''}
                                />
                                <InputError message={errors.holder_name} />
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <LabelWithHelp
                                    htmlFor={`instructions-${suffix}`}
                                    label={t(
                                        'payment_accounts.fields.instructions',
                                    )}
                                    help={t(
                                        'payment_accounts.help.instructions',
                                    )}
                                />
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

                        <InputError message={errors.confirmed} />

                        <SubmitButton
                            data-test="payment-account-submit"
                            processing={processing}
                            dirty={isDirty}
                        >
                            {account ? null : <Plus />}
                            {account
                                ? t('payment_accounts.actions.save')
                                : t('payment_accounts.actions.create')}
                        </SubmitButton>
                    </>
                )}
            </Form>

            <ConfirmActionDialog
                open={preview !== null}
                onOpenChange={(open) => !open && setPreview(null)}
                title={t('payment_accounts.confirm_immediate.title')}
                description={t(
                    'payment_accounts.confirm_immediate.description',
                )}
                confirmLabel={t('payment_accounts.confirm_immediate.confirm')}
                testId="payment-account-immediate-confirm"
                onConfirm={() => {
                    confirmed.current = true;
                    setPreview(null);
                    formRef.current?.submit();
                }}
            >
                <ConfirmSummary
                    items={[
                        {
                            label: t('payment_accounts.fields.channel'),
                            value:
                                channels.find(
                                    (option) =>
                                        option.value === preview?.channel,
                                )?.label ?? '-',
                        },
                        {
                            label: t('payment_accounts.fields.account_number'),
                            value: preview?.accountNumber || '-',
                            emphasis: true,
                            mono: true,
                        },
                        {
                            label: t('payment_accounts.fields.holder_name'),
                            value: preview?.holderName || '-',
                        },
                    ]}
                />
            </ConfirmActionDialog>
        </>
    );
}
