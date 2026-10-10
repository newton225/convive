import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    // Null quand l'organisateur masque le nombre de places (le defaut).
    remainingSeats: number | null;
    hasEnoughSeats: boolean;
};

/**
 * README ecran 9 : les places encore libres, montrees avant de relancer une reservation expiree ou
 * une preuve rejetee. Ce que l'invite a declare se lit juste au-dessus (`RegistrationSummaryCard`) :
 * il ne clique plus a l'aveugle sur « verifier les places et relancer ». Le nombre de places est un
 * instantane a l'ouverture de la page, informatif seulement, la relance revalide le stock.
 */
export function RegistrationRecap({ remainingSeats, hasEnoughSeats }: Props) {
    const { t } = useTranslation();

    return (
        <Card data-test="registration-recap">
            <CardContent className="space-y-1 pt-6">
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
