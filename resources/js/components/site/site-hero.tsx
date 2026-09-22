import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { register } from '@/routes';
import { TicketPreview } from './ticket-preview';

/**
 * Le bandeau d'accroche : pleine largeur sur fond encre, titre serre, et le produit pour heros (le
 * billet, pas une illustration). Une seule animation dominante : l'entree du texte puis celle du
 * billet, en cascade courte. Sans mouvement, tout s'affiche d'emblee.
 */
export function SiteHero() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion();

    const enter = (delay: number) =>
        reduceMotion
            ? {}
            : {
                  initial: { opacity: 0, y: 14 },
                  animate: { opacity: 1, y: 0 },
                  transition: {
                      duration: 0.5,
                      delay,
                      ease: 'easeOut' as const,
                  },
              };

    return (
        <section className="bg-ink text-white" data-test="site-hero">
            <div className="mx-auto grid w-full max-w-6xl items-center gap-12 px-6 pt-14 pb-20 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] lg:pt-20 lg:pb-28">
                <div>
                    <motion.p
                        {...enter(0)}
                        className="text-sm font-medium tracking-wide text-white/60 uppercase"
                    >
                        {t('site.hero.eyebrow')}
                    </motion.p>
                    <motion.h1
                        {...enter(0.08)}
                        className="mt-4 text-4xl leading-[1.05] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl"
                    >
                        {t('site.hero.title')}
                    </motion.h1>
                    <motion.p
                        {...enter(0.16)}
                        className="mt-6 max-w-xl text-lg leading-relaxed text-white/75"
                    >
                        {t('site.hero.description')}
                    </motion.p>
                    <motion.div
                        {...enter(0.24)}
                        className="mt-8 flex flex-wrap gap-3"
                    >
                        <Button size="lg" asChild>
                            <Link
                                href={register()}
                                data-test="site-hero-register"
                            >
                                {t('site.hero.primary')}
                            </Link>
                        </Button>
                        <Button
                            size="lg"
                            variant="ghost"
                            className="text-white hover:bg-white/10 hover:text-white"
                            asChild
                        >
                            <a href="#pricing">{t('site.hero.secondary')}</a>
                        </Button>
                    </motion.div>
                </div>

                <motion.div
                    {...enter(0.32)}
                    className="flex justify-center lg:justify-end"
                >
                    <TicketPreview />
                </motion.div>
            </div>
        </section>
    );
}
