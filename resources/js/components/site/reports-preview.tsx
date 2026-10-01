import { motion, useInView, useReducedMotion } from 'framer-motion';
import { useRef } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { EaseOut } from '@/lib/motion';

// Donnees d'exemple : des unites telles qu'une organisation les compose, et leur taux de presence.
const Units = [
    { name: 'ELIAKIM', attendance: 92 },
    { name: 'QODESH', attendance: 85 },
    { name: 'SENTINELLES', attendance: 78 },
    { name: 'ELISHAMA', attendance: 96 },
    { name: 'CHOSEN', attendance: 71 },
] as const;

/**
 * Le rapport post-evenement (README ecran 22) : presence par unite. Le pourcentage est ecrit sous
 * chaque barre ; les barres montent a l'arrivee a l'ecran, par une echelle verticale (transform).
 *
 * C'est la liste qu'on observe, pas chaque barre : une barre a l'echelle zero n'a aucune hauteur,
 * et le navigateur ne la voit jamais entrer dans l'ecran. Elle restait vide.
 */
export function ReportsPreview() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const list = useRef<HTMLUListElement>(null);
    const inView = useInView(list, { once: true, margin: '-60px' });
    const shown = reduceMotion || inView;

    return (
        <div data-test="site-reports-preview" className="space-y-3">
            <p className="text-muted-foreground text-sm">
                {t('site.preview.reports.title')}
            </p>
            <ul ref={list} className="grid grid-cols-5 items-end gap-3">
                {Units.map((unit, index) => (
                    <li key={unit.name} className="space-y-2 text-center">
                        <div className="bg-muted/60 flex h-28 items-end overflow-hidden rounded-xl">
                            <motion.div
                                className="bg-primary h-full w-full origin-bottom rounded-xl"
                                initial={reduceMotion ? false : { scaleY: 0 }}
                                animate={{
                                    scaleY: shown ? unit.attendance / 100 : 0,
                                }}
                                transition={{
                                    duration: reduceMotion ? 0 : 0.9,
                                    delay: reduceMotion
                                        ? 0
                                        : 0.15 + index * 0.08,
                                    ease: EaseOut,
                                }}
                            />
                        </div>
                        <p className="text-sm font-semibold tabular-nums">
                            {unit.attendance} %
                        </p>
                        <p className="text-muted-foreground truncate text-[0.7rem] tracking-wide">
                            {unit.name}
                        </p>
                    </li>
                ))}
            </ul>
        </div>
    );
}
