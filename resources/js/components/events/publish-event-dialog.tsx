import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { useGettingStartedReturn } from '@/hooks/use-getting-started-return';
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
    // Premiere publication de l'organisation : elle demarre le delai des comptes de versement.
    firstPublication: boolean;
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
    firstPublication,
}: Props) {
    const { t, locale } = useTranslation();
    const gettingStartedReturn = useGettingStartedReturn();
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

    const priceLabel = (price: number) =>
        price === 0
            ? t('events.price_categories.free')
            : formatAmount(price, locale);

    const rows: { label: string; value: ReactNode }[] = [
        { label: t('events.fields.name'), value: event.name },
        {
            label: t('events.fields.starts_at'),
            value: event.startsAt
                ? formatDateTime(event.startsAt, locale)
                : notSet,
        },
        { label: t('events.fields.venue'), value: event.venue ?? notSet },
        {
            label: t('events.fields.venue_map_url'),
            value: event.venueMapUrl ?? notSet,
        },
        {
            label:
                event.priceCategories.length > 0
                    ? t('events.fields.price_categories')
                    : t('events.fields.price_per_person'),
            // Un tarif par ligne : le nom a gauche, le prix aligne a droite.
            value:
                event.priceCategories.length > 0 ? (
                    <ul className="space-y-0.5">
                        {event.priceCategories.map((category) => (
                            <li
                                key={category.id}
                                className="flex justify-between gap-3"
                            >
                                <span className="break-words">
                                    {category.name}
                                </span>
                                <span className="whitespace-nowrap tabular-nums">
                                    {priceLabel(category.price)}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    priceLabel(event.pricePerPerson)
                ),
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
                    publish([tenantSlug, event.id], gettingStartedReturn).url,
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
                                <ul className="space-y-2">
                                    {accounts.map((account) => (
                                        <li key={account.id}>
                                            <span className="break-words">
                                                {account.channelLabel
                                                    ? `${account.channelLabel} · `
                                                    : ''}
                                                {account.label}
                                            </span>
                                            {/* Un numero ne se coupe jamais en deux : il tient sur sa ligne. */}
                                            {account.accountNumber ? (
                                                <span className="text-muted-foreground block whitespace-nowrap tabular-nums">
                                                    {account.accountNumber}
                                                </span>
                                            ) : null}
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
                        {firstPublication ? (
                            <li
                                className="text-foreground font-medium"
                                data-test="event-publish-payment-delay"
                            >
                                {t(
                                    'events.confirm_publish.consequence_payment_delay',
                                )}
                            </li>
                        ) : null}
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
