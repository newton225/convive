import { motion, useReducedMotion } from 'framer-motion';
import type { ReactNode } from 'react';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { HoldPreview } from './hold-preview';
import { ProofsPreview } from './proofs-preview';
import { ReportsPreview } from './reports-preview';
import { Reveal } from './reveal';
import { ScanPreview } from './scan-preview';
import { SeatingPreview } from './seating-preview';
import { SectionHeading } from './section-heading';

type Feature = {
    key: 'hold' | 'proofs' | 'seating' | 'ticket' | 'reports';
    span: string;
    wide?: boolean;
    preview: ReactNode;
};

// Une grille de cartes asymetrique : deux cartes de largeurs inegales par rangee, puis une
// bande pleine largeur. Chaque carte montre l'artefact reel qu'elle decrit, qui s'anime a
// l'arrivee a l'ecran pour montrer ce qu'il fait.
const Features: Feature[] = [
    { key: 'hold', span: 'lg:col-span-5', preview: <HoldPreview /> },
    { key: 'proofs', span: 'lg:col-span-7', preview: <ProofsPreview /> },
    { key: 'seating', span: 'lg:col-span-7', preview: <SeatingPreview /> },
    { key: 'ticket', span: 'lg:col-span-5', preview: <ScanPreview /> },
    {
        key: 'reports',
        span: 'lg:col-span-12',
        wide: true,
        preview: <ReportsPreview />,
    },
];

export function SiteFeatures() {
    const { t } = useTranslation();
    const reduceMotion = useReducedMotion() === true;

    return (
        <section
            id="features"
            className="mx-auto w-full max-w-6xl scroll-mt-20 px-6 pt-24 pb-16 lg:pt-32"
            data-test="site-features"
        >
            <SectionHeading
                eyebrow={t('site.nav.features')}
                title={t('site.features.title')}
            />

            <div className="mt-12 grid gap-4 lg:grid-cols-12">
                {Features.map((feature, index) => (
                    <Reveal
                        key={feature.key}
                        delay={(index % 2) * 0.1}
                        className={cn('flex', feature.span)}
                    >
                        <motion.article
                            whileHover={reduceMotion ? undefined : { y: -4 }}
                            transition={{ duration: 0.25, ease: 'easeOut' }}
                            className={cn(
                                'bg-card group relative flex w-full flex-col gap-8 overflow-hidden rounded-3xl p-7',
                                feature.wide &&
                                    'lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] lg:items-center lg:gap-12',
                            )}
                            data-test={`site-feature-${feature.key}`}
                        >
                            <div
                                className="site-card-glow pointer-events-none absolute inset-0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                                aria-hidden="true"
                            />
                            <div className="relative">
                                <h3 className="text-xl font-semibold tracking-tight">
                                    {t(`site.features.${feature.key}.title`)}
                                </h3>
                                <p className="text-muted-foreground mt-2 max-w-prose leading-relaxed">
                                    {t(`site.features.${feature.key}.body`)}
                                </p>
                            </div>
                            <div className="relative mt-auto">
                                {feature.preview}
                            </div>
                        </motion.article>
                    </Reveal>
                ))}
            </div>
        </section>
    );
}
