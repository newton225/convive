import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import {
    CameraOff,
    CircleCheck,
    CircleX,
    Loader2,
    Lock,
    ScanLine,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import type { ScanVerdict, ScanVerdictTone } from '@/lib/scan-verdict';
import { cn } from '@/lib/utils';

export type ViewfinderPhase =
    | 'starting'
    | 'searching'
    | 'checking'
    | 'paused'
    | 'error';

type Props = {
    phase: ViewfinderPhase;
    // Verdict du dernier billet, affiche quelques secondes par-dessus la camera.
    verdict: ScanVerdict | null;
    // Change a chaque nouveau verdict, pour rejouer l'apparition meme si le verdict est le meme.
    verdictKey: number;
};

const PhaseIcons: Record<ViewfinderPhase, LucideIcon> = {
    starting: Loader2,
    searching: ScanLine,
    checking: Loader2,
    paused: Lock,
    error: CameraOff,
};

const ToneIcons: Record<ScanVerdictTone, LucideIcon> = {
    success: CircleCheck,
    warning: TriangleAlert,
    danger: CircleX,
};

const ToneFrame: Record<ScanVerdictTone, string> = {
    success: 'border-emerald-400',
    warning: 'border-amber-400',
    danger: 'border-red-500',
};

const TonePill: Record<ScanVerdictTone, string> = {
    success: 'bg-emerald-600 text-white',
    warning: 'bg-amber-500 text-black',
    danger: 'bg-red-600 text-white',
};

const ToneTint: Record<ScanVerdictTone, string> = {
    success: 'bg-emerald-500/25',
    warning: 'bg-amber-400/25',
    danger: 'bg-red-500/30',
};

/**
 * Ce que la camera est en train de faire, dit par-dessus l'image (README ecran 26) : demarrage,
 * recherche d'un QR, code detecte et en cours de verification, puis le verdict en couleur, icone
 * et texte. L'agent regarde le viseur : c'est la qu'il doit lire qu'un billet a ete vu, sans
 * chercher le resultat ailleurs sur l'ecran. Transform et opacite seulement, sans mouvement
 * avec `prefers-reduced-motion`.
 */
export function ScanViewfinderStatus({ phase, verdict, verdictKey }: Props) {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const transition = reduceMotion
        ? { duration: 0 }
        : { duration: Duration.quick, ease: EaseOut };

    const PhaseIcon = PhaseIcons[phase];
    const VerdictIcon = verdict ? ToneIcons[verdict.tone] : null;
    const spinning = phase === 'starting' || phase === 'checking';

    return (
        <div
            className="pointer-events-none absolute inset-0"
            data-test="scan-viewfinder-status"
            data-phase={phase}
            data-verdict={verdict?.tone ?? ''}
        >
            <AnimatePresence>
                {verdict ? (
                    <motion.div
                        key={`tint-${verdictKey}`}
                        className={cn(
                            'absolute inset-0',
                            ToneTint[verdict.tone],
                        )}
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        exit={{ opacity: 0 }}
                        transition={transition}
                    />
                ) : null}
            </AnimatePresence>

            {/* Le cadre de visee : ou placer le QR, et sa couleur dit l'etat du dernier billet. */}
            <div
                className={cn(
                    'absolute inset-[14%] rounded-xl border-4 transition-colors duration-200',
                    verdict
                        ? ToneFrame[verdict.tone]
                        : phase === 'checking'
                          ? 'border-sky-400'
                          : 'border-white/70',
                    phase === 'error' || phase === 'paused' ? 'opacity-40' : '',
                )}
            >
                {phase === 'searching' && !verdict && !reduceMotion ? (
                    <motion.div
                        className="absolute inset-x-2 h-0.5 rounded-full bg-white/80"
                        initial={{ y: 0 }}
                        animate={{ y: ['0%', '9000%', '0%'] }}
                        transition={{
                            duration: 2.4,
                            ease: 'easeInOut',
                            repeat: Infinity,
                        }}
                    />
                ) : null}
            </div>

            <div className="absolute inset-x-0 bottom-3 flex justify-center px-3">
                <AnimatePresence mode="wait" initial={false}>
                    {verdict && VerdictIcon ? (
                        <motion.div
                            key={`verdict-${verdictKey}`}
                            className={cn(
                                'flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold shadow-lg',
                                TonePill[verdict.tone],
                            )}
                            initial={
                                reduceMotion
                                    ? false
                                    : { opacity: 0, y: 8, scale: 0.96 }
                            }
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            exit={
                                reduceMotion ? undefined : { opacity: 0, y: 8 }
                            }
                            transition={transition}
                            role="status"
                            aria-live="assertive"
                            data-test="scan-viewfinder-verdict"
                        >
                            <VerdictIcon className="size-5" />
                            {t(verdict.labelKey)}
                        </motion.div>
                    ) : (
                        <motion.div
                            key={`phase-${phase}`}
                            className={cn(
                                'flex items-center gap-2 rounded-full bg-black/65 px-3 py-1.5 text-xs font-medium text-white',
                                phase === 'checking' && 'bg-sky-600',
                            )}
                            initial={
                                reduceMotion ? false : { opacity: 0, y: 6 }
                            }
                            animate={{ opacity: 1, y: 0 }}
                            exit={
                                reduceMotion ? undefined : { opacity: 0, y: 6 }
                            }
                            transition={transition}
                            role="status"
                            aria-live="polite"
                            data-test="scan-viewfinder-phase"
                        >
                            <PhaseIcon
                                className={cn(
                                    'size-4',
                                    spinning && !reduceMotion && 'animate-spin',
                                )}
                            />
                            {t(`scan.viewfinder.phases.${phase}`)}
                        </motion.div>
                    )}
                </AnimatePresence>
            </div>
        </div>
    );
}
