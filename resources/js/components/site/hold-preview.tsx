import { motion, useInView, useReducedMotion } from 'framer-motion';
import { Timer } from 'lucide-react';
import { useRef } from 'react';
import { formatCountdown } from '@/hooks/use-countdown';
import { useLoopingCountdown } from '@/hooks/use-looping-countdown';
import { useTranslation } from '@/hooks/use-translation';

const HoldSeconds = 600;
const StartSeconds = 582;

/**
 * Le compte a rebours de reservation (README 2.1), tel que l'invite le voit. Il ne tourne que
 * lorsque la carte est a l'ecran, et la jauge se vide avec lui (echelle horizontale, jamais la
 * largeur). Fige a 9:42 pour qui demande moins de mouvement.
 */
export function HoldPreview() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const container = useRef<HTMLDivElement>(null);
    const inView = useInView(container, { margin: '-80px' });
    const seconds = useLoopingCountdown(StartSeconds, inView && !reduceMotion);
    const ratio = seconds / HoldSeconds;

    return (
        <div
            ref={container}
            data-test="site-hold-preview"
            className="space-y-4"
        >
            <div className="text-muted-foreground flex items-center gap-2 text-sm">
                <Timer className="size-4" />
                {t('site.preview.hold.label')}
            </div>
            <p className="text-5xl font-semibold tracking-tight tabular-nums">
                {formatCountdown(seconds)}
            </p>
            <div
                className="bg-muted h-2 overflow-hidden rounded-full"
                role="progressbar"
                aria-valuenow={Math.round(ratio * 100)}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label={t('site.preview.hold.label')}
            >
                <motion.div
                    className="bg-primary h-full origin-left rounded-full"
                    initial={false}
                    animate={{ scaleX: ratio }}
                    transition={{
                        duration: reduceMotion ? 0 : 0.9,
                        ease: 'linear',
                    }}
                />
            </div>
            <p className="text-muted-foreground text-sm">
                {t('site.preview.hold.help')}
            </p>
        </div>
    );
}
