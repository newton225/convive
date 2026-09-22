import { useTranslation } from '@/hooks/use-translation';
import { Reveal } from './reveal';

const Figures = ['no_collection', 'hold', 'reminders', 'signature'] as const;

/**
 * Le bandeau des chiffres cles. Ce ne sont pas des statistiques d'usage : ce sont des faits du
 * produit (aucun encaissement, duree de reservation par defaut, rappels, signature des billets),
 * verifiables dans son fonctionnement.
 */
export function SiteFigures() {
    const { t } = useTranslation();

    return (
        <section
            className="mx-auto w-full max-w-6xl px-6 py-14"
            aria-label={t('site.nav.features')}
            data-test="site-figures"
        >
            <dl className="grid grid-cols-[repeat(auto-fit,minmax(0,1fr))] gap-8 sm:grid-cols-2 lg:grid-cols-4">
                {Figures.map((figure, index) => (
                    <Reveal key={figure} delay={index * 0.06}>
                        <dt className="text-3xl font-semibold tracking-tight">
                            {t(`site.figures.${figure}.value`)}
                        </dt>
                        <dd className="text-muted-foreground mt-1 text-sm">
                            {t(`site.figures.${figure}.label`)}
                        </dd>
                    </Reveal>
                ))}
            </dl>
        </section>
    );
}
