import { useTranslation } from '@/hooks/use-translation';
import { Reveal } from './reveal';

const Steps = ['publish', 'register', 'pay', 'validate', 'scan'] as const;

/**
 * La frise d'etapes numerotees : elle dit ou se passe l'action, y compris quand elle a lieu hors
 * de l'application (le paiement se fait chez l'organisation, pas ici).
 */
export function SiteSteps() {
    const { t } = useTranslation();

    return (
        <section
            id="steps"
            className="mx-auto w-full max-w-6xl scroll-mt-20 px-6 py-16"
            data-test="site-steps"
        >
            <Reveal>
                <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {t('site.steps.title')}
                </h2>
            </Reveal>

            <ol className="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-5">
                {Steps.map((step, index) => (
                    <li key={step}>
                        <Reveal delay={index * 0.06}>
                            <span className="text-primary text-sm font-semibold tabular-nums">
                                {String(index + 1).padStart(2, '0')}
                            </span>
                            <h3 className="mt-2 font-semibold">
                                {t(`site.steps.${step}.title`)}
                            </h3>
                            <p className="text-muted-foreground mt-1.5 text-sm leading-relaxed">
                                {t(`site.steps.${step}.body`)}
                            </p>
                        </Reveal>
                    </li>
                ))}
            </ol>
        </section>
    );
}
