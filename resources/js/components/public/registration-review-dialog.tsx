import { ConfirmActionDialog } from '@/components/confirm-action-dialog';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { RegistrationRecap } from '@/lib/registration-recap';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    recap: RegistrationRecap | null;
    total: number;
    processing: boolean;
    onConfirm: () => void;
};

/**
 * Recapitulatif avant de reserver : l'invite relit ses informations, surtout le telephone (il sert
 * a l'envoi de la carte, des billets et des rappels), puis confirme ou revient les corriger. Aucune
 * place n'est retenue tant qu'il n'a pas confirme.
 */
export function RegistrationReviewDialog({
    open,
    onOpenChange,
    recap,
    total,
    processing,
    onConfirm,
}: Props) {
    const { t, locale } = useTranslation();

    return (
        <ConfirmActionDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('guest.registration.review.title')}
            description={t('guest.registration.review.description')}
            confirmLabel={t('guest.registration.review.confirm')}
            cancelLabel={t('guest.registration.review.edit')}
            onConfirm={onConfirm}
            processing={processing}
            testId="registration-confirm"
        >
            {recap ? (
                <div className="space-y-4" data-test="registration-recap">
                    <dl className="space-y-2 text-sm">
                        <div className="flex justify-between gap-4">
                            <dt className="text-muted-foreground">
                                {t('guest.registration.summary.phone')}
                            </dt>
                            <dd
                                className="font-medium"
                                data-test="registration-recap-phone"
                            >
                                {recap.phone}
                            </dd>
                        </div>
                        {recap.email ? (
                            <div className="flex justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    {t('guest.registration.summary.email')}
                                </dt>
                                <dd className="font-medium break-all">
                                    {recap.email}
                                </dd>
                            </div>
                        ) : null}
                    </dl>

                    <ul className="divide-y rounded-md border text-sm">
                        {recap.people.map((person, index) => (
                            <li
                                key={index}
                                className="flex items-start justify-between gap-4 p-3"
                            >
                                <div>
                                    <p className="font-medium">{person.name}</p>
                                    <p className="text-muted-foreground text-xs">
                                        {t(
                                            person.role === 'guest'
                                                ? 'guest.registration.summary.guest'
                                                : 'guest.registration.summary.companion',
                                        )}
                                        {person.unit
                                            ? ` · ${person.unit}`
                                            : ''}
                                        {person.category
                                            ? ` · ${person.category}`
                                            : ''}
                                    </p>
                                </div>
                                <span className="whitespace-nowrap">
                                    {person.price === 0
                                        ? t('guest.event.free')
                                        : formatAmount(person.price, locale)}
                                </span>
                            </li>
                        ))}
                    </ul>

                    <div className="flex items-center justify-between">
                        <span className="text-sm font-medium">
                            {t('guest.registration.total.label')}
                        </span>
                        <span
                            className="text-lg font-semibold"
                            data-test="registration-recap-total"
                        >
                            {total === 0
                                ? t('guest.event.free')
                                : formatAmount(total, locale)}
                        </span>
                    </div>

                    <p className="text-muted-foreground text-xs">
                        {t('guest.registration.review.phone_warning')}
                    </p>
                </div>
            ) : null}
        </ConfirmActionDialog>
    );
}
