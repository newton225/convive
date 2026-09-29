import { motion, useReducedMotion, useScroll } from 'framer-motion';
import { useRef } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { SectionHeading } from './section-heading';
import { SiteStep } from './site-step';

const Steps = ['publish', 'register', 'pay', 'validate', 'scan'] as const;

/**
 * La frise d'etapes numerotees : elle dit ou se passe l'action, y compris quand elle a lieu hors
 * de l'application (le paiement se fait chez l'organisation, pas ici). Seule animation de la
 * section : la ligne qui relie les etapes se trace au rythme du defilement, horizontale sur grand
 * ecran, verticale sur telephone (echelle, jamais la longueur).
 */
export function SiteSteps() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const list = useRef<HTMLDivElement>(null);
    const { scrollYProgress } = useScroll({
        target: list,
        offset: ['start 85%', 'end 55%'],
    });

    return (
        <section
            id="steps"
            className="mx-auto w-full max-w-6xl scroll-mt-20 px-6 py-24"
            data-test="site-steps"
        >
            <SectionHeading
                eyebrow={t('site.nav.steps')}
                title={t('site.steps.title')}
            />

            <div ref={list} className="relative mt-14">
                {/* Centre de la derniere pastille : 5 colonnes, 4 gouttieres de 1.5rem, 1.25rem
                    de rayon. La ligne s'y arrete au lieu de filer jusqu'au bord. */}
                <div
                    className="bg-border absolute top-5 bottom-5 left-5 w-px lg:right-[calc(20%-2.45rem)] lg:bottom-auto lg:h-px lg:w-auto"
                    aria-hidden="true"
                >
                    <motion.div
                        style={
                            reduceMotion
                                ? undefined
                                : { scaleY: scrollYProgress }
                        }
                        className="bg-primary absolute inset-0 origin-top lg:hidden"
                    />
                    <motion.div
                        style={
                            reduceMotion
                                ? undefined
                                : { scaleX: scrollYProgress }
                        }
                        className="bg-primary absolute inset-0 hidden origin-left lg:block"
                    />
                </div>

                <ol className="relative grid gap-10 lg:grid-cols-5 lg:gap-6">
                    {Steps.map((step, index) => (
                        <SiteStep
                            key={step}
                            number={index + 1}
                            title={t(`site.steps.${step}.title`)}
                            body={t(`site.steps.${step}.body`)}
                            progress={scrollYProgress}
                            reachedAt={index / (Steps.length - 1)}
                            reduceMotion={reduceMotion}
                        />
                    ))}
                </ol>
            </div>
        </section>
    );
}
