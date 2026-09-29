import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import { formatDateTime } from '@/lib/format-date';
import { publish } from '@/routes/tenants/events';
import type { EventDetails, EventPaymentAccountOption } from '@/types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    tenantSlug: string;
    event: EventDetails;
    paymentAccounts: EventPaymentAccountOption[];
};

/**
 * Publier ne se defait pas : le lien part chez les invites, le sous-domaine se fige, l'evenement
 * ne se supprime plus. Le risque ici est l'erreur de bonne foi (mauvaise date, mauvais tarif),
 * pas l'intrus : plutot qu'un mot de passe, qui ne fait rien relire, la fenetre montre ce que
 * l'invite verra et exige une case cochee avant d'activer le bouton. Le recapitulatif reprend
 * les valeurs enregistrees, pas celles du formulaire en cours d'edition, puisque ce sont elles
 * que le serveur publie.
 */
export function PublishEventDialog({
    open,
    onOpenChange,
    tenantSlug,
    event,
    paymentAccounts,
}: Props) {
    const { t, locale } = useTranslation();
    const [checked, setChecked] = useState(false);
    const [publishing, setPublishing] = useState(false);

    const accounts = paymentAccounts.filter((account) =>
        event.paymentAccountIds.includes(account.id),
    );
    const notSet = t('events.confirm_publish.not_set');

    const changeOpen = (next: boolean) => {
        if (!next) {
            setChecked(false);
        }

        onOpenChange(next);
    };

    const rows: { label: string; value: string }[] = [
        { label: t('events.fields.name'), value: event.name },
        {
            label: t('events.fields.starts_at'),
            value: event.startsAt
                ? formatDateTime(event.startsAt, locale)
                : notSet,
        },
        { label: t('events.fields.venue'), value: event.venue ?? notSet },
        {
            label: t('events.fields.price_per_person'),
            value: formatAmount(event.pricePerPerson, locale),
        },
        {
            label: t('events.confirm_publish.capacity'),
            value: t('events.confirm_publish.seats', {
                count: event.capacity,
            }),
        },
        {
            label: t('events.fields.registration_deadline'),
            value: event.registrationDeadline
                ? formatDateTime(event.registrationDeadline, locale)
                : notSet,
        },
    ];

    return (
        <ConfirmActionDialog
            open={open}
            onOpenChange={changeOpen}
            title={t('events.confirm_publish.title')}
            description={t('events.confirm_publish.description')}
            confirmLabel={t('events.actions.publish')}
            processing={publishing}
            confirmDisabled={!checked}
            testId="event-publish-confirm"
            onConfirm={() =>
                router.post(
                    publish([tenantSlug, event.id]).url,
                    {},
                    {
                        onStart: () => setPublishing(true),
                        onFinish: () => setPublishing(false),
                        onSuccess: () => changeOpen(false),
                    },
                )
            }
        >
            <div className="space-y-4 text-sm">
                <div className="space-y-2">
                    <p className="font-medium">
                        {t('events.confirm_publish.summary')}
                    </p>
                    <dl
                        className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-x-3 gap-y-1.5 rounded-lg border p-3"
                        data-test="event-publish-summary"
                    >
                        {rows.map((row) => (
                            <div key={row.label} className="contents">
                                <dt className="text-muted-foreground">
                                    {row.label}
                                </dt>
                                <dd className="break-words">{row.value}</dd>
                            </div>
                        ))}
                        <dt className="text-muted-foreground">
                            {t('events.fields.payment_accounts')}
                        </dt>
                        <dd>
                            {accounts.length === 0 ? (
                                notSet
                            ) : (
                                <ul className="space-y-0.5">
                                    {accounts.map((account) => (
                                        <li
                                            key={account.id}
                                            className="break-words"
                                        >
                                            {account.channelLabel
                                                ? `${account.channelLabel} · `
                                                : ''}
                                            {account.label}
                                            {account.accountNumber
                                                ? ` · ${account.accountNumber}`
                                                : ''}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </dd>
                    </dl>
                    <p className="text-muted-foreground text-xs">
                        {t('events.confirm_publish.saved_values')}
                    </p>
                </div>

                <div className="space-y-1">
                    <p className="font-medium">
                        {t('events.confirm_publish.consequences')}
                    </p>
                    <ul className="text-muted-foreground list-disc space-y-0.5 pl-5">
                        <li>{t('events.confirm_publish.consequence_link')}</li>
                        <li>
                            {t('events.confirm_publish.consequence_subdomain')}
                        </li>
                        <li>
                            {t('events.confirm_publish.consequence_delete')}
                        </li>
                    </ul>
                </div>

                <label className="flex min-h-11 items-start gap-2">
                    <Checkbox
                        className="mt-0.5"
                        checked={checked}
                        data-test="event-publish-acknowledge"
                        onCheckedChange={(value) => setChecked(value === true)}
                    />
                    {t('events.confirm_publish.acknowledge')}
                </label>
            </div>
        </ConfirmActionDialog>
    );
}
