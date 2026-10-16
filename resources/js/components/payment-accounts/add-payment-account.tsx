import { Plus } from 'lucide-react';
import { useState } from 'react';
import { AccountForm } from '@/components/payment-accounts/account-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { store } from '@/routes/tenants/payment-accounts';
import type { PaymentChannelOption } from '@/types';

type Props = {
    tenantSlug: string;
    // Ce que l'ecran « Pour commencer » ajoute a l'adresse pour y ramener la personne.
    gettingStartedReturn: Parameters<typeof store>[1];
    channels: PaymentChannelOption[];
    delayActive: boolean;
    hasAccounts: boolean;
};

/**
 * Le formulaire d'ajout, toujours en tete de l'ecran et seul formulaire ouvert : c'est la seule
 * chose a remplir. Quand des comptes existent deja, il se replie derriere un bouton pour ne pas
 * pousser les comptes hors de vue ; sans aucun compte, il est ouvert d'office.
 */
export function AddPaymentAccount({
    tenantSlug,
    gettingStartedReturn,
    channels,
    delayActive,
    hasAccounts,
}: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    if (hasAccounts && !open) {
        return (
            <Button
                type="button"
                variant="outline"
                className="self-start"
                data-test="payment-account-add"
                onClick={() => setOpen(true)}
            >
                <Plus />
                {t('payment_accounts.actions.create')}
            </Button>
        );
    }

    return (
        <div
            className="border-primary/40 bg-primary/5 space-y-4 rounded-lg border p-4"
            data-test="payment-account-new"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex flex-wrap items-center gap-2">
                    <h2 className="font-medium">
                        {t('payment_accounts.actions.create')}
                    </h2>
                    <Badge>{t('payment_accounts.card.to_fill')}</Badge>
                </div>
                {hasAccounts ? (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-test="payment-account-add-close"
                        onClick={() => setOpen(false)}
                    >
                        {t('common.actions.cancel')}
                    </Button>
                ) : null}
            </div>

            <AccountForm
                action={store.form(tenantSlug, gettingStartedReturn)}
                account={null}
                channels={channels}
                delayActive={delayActive}
            />
        </div>
    );
}
