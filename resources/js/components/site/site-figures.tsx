import { useTranslation } from '@/hooks/use-translation';
import { Reveal } from './reveal';

const Figures = ['no_collection', 'hold', 'reminders', 'signature'] as const;

/**
 * Le bandeau des chiffres cles, pose a cheval sur la fin de l'accroche. Ce ne sont pas des
 * statistiques d'usage : ce sont des faits du produit (aucun encaissement, duree de reservation
 * par defaut, rappels, signature des billets), verifiables dans son fonctionnement.
 */
export function SiteFigures() {
    const { t } = useTranslation();

    return (
        <section
            className="relative z-10 mx-auto -mt-12 w-full max-w-6xl px-6"
            aria-label={t('site.nav.features')}
            data-test="site-figures"
        >
            <Reveal>
                <dl className="bg-card grid gap-px overflow-hidden rounded-3xl sm:grid-cols-2 lg:grid-cols-4">
                    {Figures.map((figure) => (
                        <div
                            key={figure}
                            className="bg-card border-border/70 p-7 sm:odd:border-r lg:border-r lg:last:border-r-0"
                        >
                            <dt className="text-3xl font-semibold tracking-tight tabular-nums lg:text-4xl">
                                {t(`site.figures.${figure}.value`)}
                            </dt>
                            <dd className="text-muted-foreground mt-2 text-sm leading-relaxed">
                                {t(`site.figures.${figure}.label`)}
                            </dd>
                        </div>
                    ))}
                </dl>
            </Reveal>
        </section>
    );
}
