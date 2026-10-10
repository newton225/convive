import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';
import { formatAmount } from '@/lib/format-currency';
import type { RegistrationShow } from '@/types';

type Props = {
    registration: Pick<
        RegistrationShow,
        'name' | 'unit' | 'amountDue' | 'companions'
    >;
    // Null quand l'organisateur masque le nombre de places (le defaut).
    remainingSeats: number | null;
    hasEnoughSeats: boolean;
};

/**
 * README ecran 9 : le recapitulatif montre avant de relancer une reservation expiree ou une
 * preuve rejetee, avec les places encore libres. L'invite ne clique plus a l'aveugle sur
 * « verifier les places et relancer » : il voit ce qu'il a declare et si une place a des chances
 * de rester. Le nombre de places est informatif seulement, la relance revalide le stock.
 */
export function RegistrationRecap({
    registration,
    remainingSeats,
    hasEnoughSeats,
}: Props) {
    const { t, locale } = useTranslation();

    return (
        <Card data-test="registration-recap">
            <CardContent className="space-y-3 pt-6">
                <p className="font-medium">
                    {t('guest.registration.show.recap_title')}
                </p>

                <dl className="space-y-1 text-sm">
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">
                            {t('guest.registration.fields.name')}
                        </dt>
                        <dd className="font-medium">{registration.name}</dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">
                            {t('guest.registration.fields.unit')}
                        </dt>
                        <dd>{registration.unit}</dd>
                    </div>
                    {registration.companions.length > 0 ? (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">
                                {t('guest.ticket.guests_title')}
                            </dt>
                            <dd>
                                {registration.companions
                                    .map((companion) => companion.name)
                                    .join(', ')}
                            </dd>
                        </div>
                    ) : null}
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">
                            {t('guest.registration.total.label')}
                        </dt>
                        <dd className="font-medium">
                            {formatAmount(registration.amountDue, locale)}
                        </dd>
                    </div>
                </dl>

                <p
                    className={
                        hasEnoughSeats
                            ? 'text-sm'
                            : 'text-destructive text-sm font-medium'
                    }
                    data-test="recap-seats"
                    role={hasEnoughSeats ? undefined : 'alert'}
                >
                    {remainingSeats !== null
                        ? t('guest.registration.show.seats_available', {
                              count: remainingSeats,
                          })
                        : t(
                              hasEnoughSeats
                                  ? 'guest.registration.show.seats_enough'
                                  : 'guest.registration.show.seats_not_enough',
                          )}
                </p>
                <p className="text-muted-foreground text-xs">
                    {t('guest.registration.show.seats_not_reserved')}
                </p>
            </CardContent>
        </Card>
    );
}
