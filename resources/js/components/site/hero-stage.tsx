import { motion, useReducedMotion } from 'framer-motion';
import { CircleCheck, ScanLine, Timer } from 'lucide-react';
import { formatCountdown } from '@/hooks/use-countdown';
import { useLoopingCountdown } from '@/hooks/use-looping-countdown';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import { TicketPreview } from './ticket-preview';

/**
 * La scene de l'accroche : le billet au centre, et autour de lui les trois temps du produit qui
 * s'enchainent dans l'ordre ou ils arrivent a l'invite. La reservation decompte, la preuve est
 * validee, l'entree est acceptee. Le mouvement raconte la causalite (CLAUDE.md, « Le produit comme
 * heros »). Sans mouvement, tout est pose d'emblee et le decompte reste fige.
 */
export function HeroStage() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const seconds = useLoopingCountdown(582, !reduceMotion);

    const appear = (delay: number, from: { x?: number; y?: number }) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, ...from },
                  animate: { opacity: 1, x: 0, y: 0 },
                  transition: { duration: Duration.slow, delay, ease: EaseOut },
              };

    return (
        <div className="relative mx-auto w-full max-w-md py-10 lg:py-14">
            <motion.div
                {...appear(0.35, { y: 24 })}
                className="relative z-10 flex justify-center"
            >
                <motion.div
                    animate={reduceMotion ? undefined : { y: [0, -8, 0] }}
                    transition={{
                        duration: 6,
                        repeat: Infinity,
                        ease: 'easeInOut',
                    }}
                    className="w-full max-w-sm"
                >
                    <TicketPreview />
                </motion.div>
            </motion.div>

            <motion.div
                {...appear(0.9, { x: -16 })}
                className="bg-ink/80 absolute top-0 left-0 z-20 flex items-center gap-3 rounded-xl border border-white/10 px-3.5 py-2.5 text-white backdrop-blur-md sm:-left-8"
                data-test="site-hero-hold"
            >
                <span className="bg-primary/25 text-primary-foreground flex size-8 items-center justify-center rounded-lg">
                    <Timer className="size-4" />
                </span>
                <span>
                    <span className="block text-xs text-white/60">
                        {t('site.hero.stage.hold')}
                    </span>
                    <span className="block font-semibold tabular-nums">
                        {formatCountdown(seconds)}
                    </span>
                </span>
            </motion.div>

            <motion.div
                {...appear(1.6, { y: 16 })}
                className="bg-card text-card-foreground absolute right-0 bottom-2 z-20 flex max-w-[16rem] items-start gap-3 rounded-xl px-3.5 py-3 sm:-right-6"
                role="status"
                data-test="site-hero-proof"
            >
                <CircleCheck className="text-primary mt-0.5 size-5 shrink-0" />
                <span>
                    <span className="block text-sm font-semibold">
                        {t('site.hero.stage.proof_title')}
                    </span>
                    <span className="text-muted-foreground block text-xs">
                        {t('site.hero.stage.proof_body')}
                    </span>
                </span>
            </motion.div>

            <motion.div
                {...appear(2.2, { x: 16 })}
                className="bg-ink/80 absolute top-1/3 -right-2 z-0 hidden items-center gap-2 rounded-full border border-white/10 px-3 py-1.5 text-xs text-white backdrop-blur-md sm:flex lg:-right-12"
            >
                <ScanLine className="size-3.5" />
                {t('site.hero.stage.scan')}
            </motion.div>
        </div>
    );
}
