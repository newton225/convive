import { Head } from '@inertiajs/react';
import { SiteCta } from '@/components/site/site-cta';
import { SiteFeatures } from '@/components/site/site-features';
import { SiteFigures } from '@/components/site/site-figures';
import { SiteFooter } from '@/components/site/site-footer';
import { SiteHeader } from '@/components/site/site-header';
import { SiteHero } from '@/components/site/site-hero';
import { SitePricing } from '@/components/site/site-pricing';
import { SiteSteps } from '@/components/site/site-steps';
import { useTranslation } from '@/hooks/use-translation';
import type { SitePlan } from '@/types';

type Props = {
    plans: SitePlan[];
    defaultCurrency: string;
};

/**
 * La vitrine du produit (CLAUDE.md, « Animation et site produit ») : accroche pleine largeur sur
 * fond encre, chiffres cles, artefacts reels du produit, frise d'etapes, tarifs avec un palier mis
 * en avant, bande de conclusion. Sans layout : la vitrine n'a ni barre laterale ni fil d'Ariane.
 */
export default function Welcome({ plans, defaultCurrency }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('site.hero.eyebrow')} />

            <SiteHeader />

            <main data-smooth-scroll>
                <SiteHero />
                <SiteFigures />
                <SiteFeatures />
                <SiteSteps />
                <SitePricing plans={plans} currency={defaultCurrency} />
            </main>

            <SiteCta />
            <SiteFooter />
        </>
    );
}
