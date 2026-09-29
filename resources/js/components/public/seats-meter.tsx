import { motion, useReducedMotion } from 'framer-motion';
import { useTranslation } from '@/hooks/use-translation';
import { EaseOut } from '@/lib/motion';

type Props = {
    capacity: number;
    remainingSeats: number;
    isFull: boolean;
};

/**
 * Les places restantes (README ecran 3), ecrites en toutes lettres, avec une jauge des places
 * prises aux couleurs de l'organisation. La jauge n'est qu'un renfort : elle se remplit a
 * l'affichage par une echelle horizontale, jamais par la largeur.
 */
export function SeatsMeter({ capacity, remainingSeats, isFull }: Props) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const taken = Math.max(0, capacity - remainingSeats);
    const ratio = capacity > 0 ? Math.min(1, taken / capacity) : 0;

    return (
        <div className="space-y-2">
            <div className="flex items-baseline justify-between gap-3">
                <p className="font-semibold">
                    {isFull
                        ? t('guest.event.seats.full')
                        : t('guest.event.seats.remaining', {
                              count: remainingSeats,
                          })}
                </p>
                {capacity > 0 ? (
                    <p className="text-muted-foreground text-xs tabular-nums">
                        {t('guest.event.seats_taken', {
                            taken: String(taken),
                            capacity: String(capacity),
                        })}
                    </p>
                ) : null}
            </div>
            <div
                className="bg-muted h-2 overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={Math.round(ratio * 100)}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={t('guest.event.seats_taken', {
                    taken: String(taken),
                    capacity: String(capacity),
                })}
            >
                <motion.div
                    className="brand-fill h-full origin-left rounded-full"
                    initial={reduceMotion ? false : { scaleX: 0 }}
                    animate={{ scaleX: ratio }}
                    transition={{ duration: 1, delay: 0.4, ease: EaseOut }}
                />
            </div>
        </div>
    );
}
