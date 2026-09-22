import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { HoldPreview } from './hold-preview';
import { ProofsPreview } from './proofs-preview';
import { Reveal } from './reveal';
import { ScanPreview } from './scan-preview';
import { SeatingPreview } from './seating-preview';

type Feature = {
    key: 'hold' | 'proofs' | 'seating' | 'ticket' | 'reports';
    span: string;
    preview: ReactNode | null;
};

// Une grille de cartes asymetrique : deux cartes de largeurs inegales par rangee, puis une
// bande pleine largeur. Chaque carte montre l'artefact reel qu'elle decrit.
const Features: Feature[] = [
    { key: 'hold', span: 'lg:col-span-5', preview: <HoldPreview /> },
    { key: 'proofs', span: 'lg:col-span-7', preview: <ProofsPreview /> },
    { key: 'seating', span: 'lg:col-span-7', preview: <SeatingPreview /> },
    { key: 'ticket', span: 'lg:col-span-5', preview: <ScanPreview /> },
    { key: 'reports', span: 'lg:col-span-12', preview: null },
];

export function SiteFeatures() {
    const { t } = useTranslation();

    return (
        <section
            id="features"
            className="mx-auto w-full max-w-6xl scroll-mt-20 px-6 py-16"
            data-test="site-features"
        >
            <Reveal>
                <h2 className="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {t('site.features.title')}
                </h2>
            </Reveal>

            <div className="mt-10 grid gap-4 lg:grid-cols-12">
                {Features.map((feature, index) => (
                    <Reveal
                        key={feature.key}
                        delay={(index % 2) * 0.08}
                        className={cn('flex', feature.span)}
                    >
                        <article
                            className="bg-card flex w-full flex-col gap-6 rounded-2xl p-6"
                            data-test={`site-feature-${feature.key}`}
                        >
                            <div>
                                <h3 className="text-lg font-semibold">
                                    {t(`site.features.${feature.key}.title`)}
                                </h3>
                                <p className="text-muted-foreground mt-2 max-w-prose text-sm leading-relaxed">
                                    {t(`site.features.${feature.key}.body`)}
                                </p>
                            </div>
                            {feature.preview ? (
                                <div className="mt-auto">{feature.preview}</div>
                            ) : null}
                        </article>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
