import { motion, useReducedMotion } from 'framer-motion';
import { useTranslation } from '@/hooks/use-translation';
import { EaseOut } from '@/lib/motion';

const Tables = [
    { number: 1, seated: 8 },
    { number: 2, seated: 10 },
    { number: 3, seated: 5 },
    { number: 4, seated: 10 },
    { number: 5, seated: 3 },
    { number: 6, seated: 7 },
] as const;

/**
 * Le plan de salle (README ecran 21) : chaque table montre ses places prises. Le nombre est ecrit,
 * la jauge n'est qu'un renfort visuel ; elle se remplit a l'arrivee a l'ecran, table apres table,
 * par une echelle horizontale (transform), jamais par la largeur.
 */
export function SeatingPreview() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;

    return (
        <div data-test="site-seating-preview" className="space-y-3">
            <p className="text-muted-foreground text-sm">
                {t('site.preview.seating.title')}
            </p>
            <ul className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                {Tables.map((table, index) => (
                    <li
                        key={table.number}
                        className="bg-muted/60 space-y-2 rounded-xl p-3"
                    >
                        <div className="flex items-baseline justify-between text-xs">
                            <span className="font-medium">
                                {t('site.preview.seating.table', {
                                    number: table.number,
                                })}
                            </span>
                            <span className="text-muted-foreground tabular-nums">
                                {table.seated}/10
                            </span>
                        </div>
                        <div className="bg-background h-1.5 overflow-hidden rounded-full">
                            <motion.div
                                className={`h-full origin-left rounded-full ${table.seated === 10 ? 'bg-foreground' : 'bg-primary'}`}
                                initial={reduceMotion ? false : { scaleX: 0 }}
                                whileInView={{ scaleX: table.seated / 10 }}
                                animate={
                                    reduceMotion
                                        ? { scaleX: table.seated / 10 }
                                        : undefined
                                }
                                viewport={{ once: true, margin: '-60px' }}
                                transition={{
                                    duration: 0.8,
                                    delay: 0.15 + index * 0.08,
                                    ease: EaseOut,
                                }}
                            />
                        </div>
                    </li>
                ))}
            </ul>
        </div>
    );
}
