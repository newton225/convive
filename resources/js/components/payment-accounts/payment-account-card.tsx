import { ChevronDown, Clock, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { AccountForm } from '@/components/payment-accounts/account-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateTime } from '@/lib/format-date';
import { update } from '@/routes/tenants/payment-accounts';
import type { PaymentAccount, PaymentChannelOption } from '@/types';

type Props = {
    tenantSlug: string;
    account: PaymentAccount;
    channels: PaymentChannelOption[];
    delayActive: boolean;
    onApprove: (account: PaymentAccount) => void;
    onCancel: (account: PaymentAccount) => void;
    onDelete: (account: PaymentAccount) => void;
};

/**
 * Un compte de versement deja renseigne : une carte qui se lit sans l'ouvrir (libelle, canal, numero,
 * titulaire, etat), et dont le formulaire ne s'ouvre qu'a la demande. Le formulaire d'ajout est
 * l'unique formulaire ouvert de l'ecran, en tete : on ne confond plus ce qui est fait et ce qui est a
 * faire. Une demande de changement en attente reste toujours visible, carte fermee ou non : elle ne
 * doit jamais se cacher derriere « Modifier » (CLAUDE.md, « Comptes de versement »).
 */
export function PaymentAccountCard({
    tenantSlug,
    account,
    channels,
    delayActive,
    onApprove,
    onCancel,
    onDelete,
}: Props) {
    const { t, locale } = useTranslation();
    const [editing, setEditing] = useState(false);

    return (
        <div
            data-test="payment-account-row"
            className="space-y-4 rounded-lg border p-4"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="font-medium">{account.label}</span>
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
                    <p
                        className="text-muted-foreground text-sm break-words"
                        data-test="payment-account-summary"
                    >
                        {account.accountNumber === null
                            ? t('payment_accounts.card.no_live')
                            : [
                                  account.channelLabel,
                                  account.accountNumber,
                                  account.holderName,
                              ]
                                  .filter(Boolean)
                                  .join(' · ')}
                    </p>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    aria-expanded={editing}
                    data-test="payment-account-edit"
                    onClick={() => setEditing((open) => !open)}
                >
                    <ChevronDown
                        className={`transition-transform duration-200 ${editing ? 'rotate-180' : ''}`}
                    />
                    {t(
                        editing
                            ? 'payment_accounts.card.close'
                            : 'payment_accounts.card.edit',
                    )}
                </Button>
            </div>

            {account.pending ? (
                <div
                    className="bg-muted space-y-1 rounded-md p-3 text-sm"
                    data-test="payment-account-pending"
                >
                    <p className="font-medium">
                        {t('payment_accounts.pending.title')}
                    </p>
                    {/* Toute la demande, pas seulement le numero : les champs du formulaire
                        montrent les valeurs actives, vides pour un compte neuf. */}
                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-3 gap-y-0.5">
                        <dt className="text-muted-foreground">
                            {t('payment_accounts.fields.channel')}
                        </dt>
                        <dd>{account.pending.channelLabel ?? ''}</dd>
                        <dt className="text-muted-foreground">
                            {t('payment_accounts.fields.account_number')}
                        </dt>
                        <dd>
                            {account.pending.accountNumber ??
                                t('payment_accounts.pending.none')}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('payment_accounts.fields.holder_name')}
                        </dt>
                        <dd>
                            {account.pending.holderName ??
                                t('payment_accounts.pending.none')}
                        </dd>
                    </dl>
                    <p className="text-muted-foreground">
                        {t('payment_accounts.pending.requested_by', {
                            name: account.pending.requestedBy ?? '',
                        })}{' '}
                        {t(
                            account.neverActive
                                ? 'payment_accounts.pending.activates_at_new'
                                : 'payment_accounts.pending.activates_at',
                            {
                                date: formatDateTime(
                                    account.pending.activatesAt ?? '',
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
                                onClick={() => onApprove(account)}
                            >
                                {t('payment_accounts.actions.approve')}
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
                            onClick={() => onCancel(account)}
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

            {editing ? (
                <div className="space-y-4 border-t pt-4">
                    <AccountForm
                        action={update.form([tenantSlug, account.id])}
                        account={account}
                        channels={channels}
                        delayActive={delayActive}
                    />

                    <Button
                        variant="ghost"
                        size="sm"
                        data-test="payment-account-delete"
                        onClick={() => onDelete(account)}
                    >
                        <Trash2 className="h-4 w-4" />
                        {t('payment_accounts.actions.delete')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
