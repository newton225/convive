import type { MotionValue } from 'framer-motion';
import { motion, useTransform } from 'framer-motion';

type Props = {
    number: number;
    title: string;
    body: string;
    // Avancement du defilement dans la frise (0 a 1), et le point ou cette etape est atteinte.
    progress: MotionValue<number>;
    reachedAt: number;
    reduceMotion: boolean;
};

/**
 * Une etape de la frise : son numero s'allume quand la ligne de progression l'atteint. Opacite et
 * echelle seulement. Sans mouvement, toutes les etapes sont allumees d'emblee.
 */
export function SiteStep({
    number,
    title,
    body,
    progress,
    reachedAt,
    reduceMotion,
}: Props) {
    const opacity = useTransform(
        progress,
        [Math.max(0, reachedAt - 0.12), reachedAt],
        [0.35, 1],
    );
    const scale = useTransform(
        progress,
        [Math.max(0, reachedAt - 0.12), reachedAt],
        [0.85, 1],
    );

    return (
        <li className="relative pl-14 lg:pl-0">
            <motion.span
                style={reduceMotion ? undefined : { opacity, scale }}
                className="bg-primary text-primary-foreground ring-background absolute top-0 left-0 z-10 flex size-10 items-center justify-center rounded-full text-sm font-semibold tabular-nums ring-8 lg:relative"
            >
                {String(number).padStart(2, '0')}
            </motion.span>
            <motion.div
                style={reduceMotion ? undefined : { opacity }}
                className="lg:mt-6"
            >
                <h3 className="text-lg font-semibold tracking-tight">
                    {title}
                </h3>
                <p className="text-muted-foreground mt-2 text-sm leading-relaxed">
                    {body}
                </p>
            </motion.div>
        </li>
    );
}
