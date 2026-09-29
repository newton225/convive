import { Link } from '@inertiajs/react';
import {
    motion,
    useReducedMotion,
    useScroll,
    useTransform,
} from 'framer-motion';
import { ArrowRight, Sparkles } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { Duration, EaseOut } from '@/lib/motion';
import { register } from '@/routes';
import { HeroStage } from './hero-stage';

const TitleLines = ['fill', 'verify', 'control'] as const;

/**
 * Le bandeau d'accroche, pleine largeur sur fond encre : grille estompee et halos indigo en fond,
 * titre serre en trois temps, et le produit pour heros (la scene du billet, pas une
 * illustration). Une seule animation dominante : l'entree en cascade, puis la scene qui se
 * raconte. Au defilement, la scene s'eloigne un peu plus lentement que le texte (translation
 * seulement). Sans mouvement, tout s'affiche d'emblee.
 */
export function SiteHero() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const section = useRef<HTMLElement>(null);
    const { scrollYProgress } = useScroll({
        target: section,
        offset: ['start start', 'end start'],
    });
    const stageY = useTransform(scrollYProgress, [0, 1], [0, 90]);
    const glowY = useTransform(scrollYProgress, [0, 1], [0, -60]);

    const enter = (delay: number) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 18 },
                  animate: { opacity: 1, y: 0 },
                  transition: { duration: Duration.base, delay, ease: EaseOut },
              };

    return (
        <section
            ref={section}
            className="bg-ink relative isolate overflow-hidden text-white"
            data-test="site-hero"
        >
            <div
                className="site-grid pointer-events-none absolute inset-0 -z-10"
                aria-hidden="true"
            />
            <motion.div
                style={reduceMotion ? undefined : { y: glowY }}
                className="pointer-events-none absolute inset-0 -z-10"
                aria-hidden="true"
            >
                <motion.div
                    className="site-glow absolute -top-40 left-1/2 size-[42rem] -translate-x-1/2"
                    animate={
                        reduceMotion
                            ? undefined
                            : { x: [-40, 40, -40], opacity: [0.8, 1, 0.8] }
                    }
                    transition={{
                        duration: 14,
                        repeat: Infinity,
                        ease: 'easeInOut',
                    }}
                />
                <motion.div
                    className="site-glow-warm absolute right-[-10rem] bottom-[-12rem] size-[34rem]"
                    animate={reduceMotion ? undefined : { y: [0, -30, 0] }}
                    transition={{
                        duration: 12,
                        repeat: Infinity,
                        ease: 'easeInOut',
                    }}
                />
            </motion.div>

            <div className="mx-auto grid w-full max-w-6xl items-center gap-8 px-6 pt-16 pb-20 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)] lg:gap-12 lg:pt-24 lg:pb-32">
                <div>
                    <motion.p
                        {...enter(0)}
                        className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-3 py-1 text-xs font-medium text-white/80 backdrop-blur"
                    >
                        <Sparkles className="text-primary size-3.5" />
                        {t('site.hero.badge')}
                    </motion.p>

                    {/* Une phrase par ligne sur grand ecran : la colonne de texte fait un peu plus
                        de la moitie de la page, au-dela de 2.75rem chaque phrase se coupait en deux. */}
                    <h1 className="mt-6 text-[2rem] leading-[1.08] font-semibold tracking-tight sm:text-5xl lg:text-[2.75rem]">
                        {TitleLines.map((line, index) => (
                            <motion.span
                                key={line}
                                {...enter(0.08 + index * 0.1)}
                                className={
                                    index === TitleLines.length - 1
                                        ? 'site-gradient-text block pb-1'
                                        : 'block'
                                }
                            >
                                {t(`site.hero.title_lines.${line}`)}
                            </motion.span>
                        ))}
                    </h1>

                    <motion.p
                        {...enter(0.42)}
                        className="mt-6 max-w-xl text-lg leading-relaxed text-white/70"
                    >
                        {t('site.hero.description')}
                    </motion.p>

                    <motion.div
                        {...enter(0.52)}
                        className="mt-9 flex flex-wrap items-center gap-3"
                    >
                        <Button
                            size="lg"
                            className="group h-12 rounded-full px-6"
                            asChild
                        >
                            <Link
                                href={register()}
                                data-test="site-hero-register"
                            >
                                {t('site.hero.primary')}
                                <ArrowRight className="transition-transform duration-200 group-hover:translate-x-0.5" />
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="ghost"
                            className="h-12 rounded-full px-6 text-white hover:bg-white/10 hover:text-white"
                            asChild
                        >
                            <a href="#pricing">{t('site.hero.secondary')}</a>
                        </Button>
                    </motion.div>

                    <motion.p
                        {...enter(0.6)}
                        className="mt-5 text-sm text-white/50"
                    >
                        {t('site.hero.reassurance')}
                    </motion.p>
                </div>

                <motion.div style={reduceMotion ? undefined : { y: stageY }}>
                    <HeroStage />
                </motion.div>
            </div>
        </section>
    );
}
