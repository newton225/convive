import { motion, useReducedMotion } from 'framer-motion';
import { ArrowUpRight, CalendarDays } from 'lucide-react';
import { useTranslation } from '@/hooks/use-translation';
import { formatDateParts } from '@/lib/format-date';
import { EaseOut } from '@/lib/motion';
import { cn } from '@/lib/utils';
import type { ShowcaseEvent } from '@/types';

type Props = {
    event: ShowcaseEvent;
    index: number;
    featured?: boolean;
};

/**
 * Une carte de la vitrine, cliquable en entier : elle mene a l'adresse complete avec jeton de
 * l'evenement, jamais a une seconde facon d'y acceder. La date se lit en vignette.
 *
 * Aucune dimension ne vient de l'image : un visuel depose peut avoir n'importe quel format, et
 * c'est lui qui etirait toute la grille. Carte ordinaire : visuel en 16/10, corps compact. Carte
 * mise en avant : une affiche, visuel en plein cadre et texte pose dessus sur un voile sombre, a
 * la hauteur des deux cartes voisines sur grand ecran.
 */
export function ShowcaseCard({ event, index, featured = false }: Props) {
    const { t, locale } = useTranslation();
    const reduceMotion = useReducedMotion() === true;
    const date = event.startsAt
        ? formatDateParts(event.startsAt, locale)
        : null;

    const visual = event.visualUrl ? (
        <img
            src={event.visualUrl}
            alt=""
            loading="lazy"
            className="absolute inset-0 size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
            data-test="showcase-visual"
        />
    ) : (
        <div className="bg-ink absolute inset-0" aria-hidden="true">
            <div className="site-glow absolute inset-0 opacity-70" />
            <CalendarDays className="absolute top-1/2 left-1/2 size-10 -translate-x-1/2 -translate-y-1/2 text-white/50" />
        </div>
    );

    const dateTile = date ? (
        <div className="bg-background/90 text-foreground absolute top-4 left-4 z-10 flex min-w-12 flex-col items-center rounded-xl px-2.5 py-1.5 leading-none backdrop-blur-md">
            <span className="text-primary text-[0.65rem] font-semibold tracking-wide uppercase">
                {date.month}
            </span>
            <span className="mt-1 text-lg font-semibold tabular-nums">
                {date.day}
            </span>
        </div>
    ) : null;

    const when = date
        ? `${date.weekday}, ${date.time}`
        : t('showcase.card.date_unknown');

    const cta = (
        <span className="inline-flex shrink-0 items-center gap-1 font-medium">
            {t('showcase.card.cta')}
            <ArrowUpRight className="size-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5" />
        </span>
    );

    return (
        <motion.a
            href={event.publicUrl}
            target="_blank"
            rel="noopener noreferrer"
            initial={reduceMotion ? false : { opacity: 0, y: 24 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: '-60px' }}
            transition={{
                duration: 0.5,
                delay: (index % 3) * 0.08,
                ease: EaseOut,
            }}
            className={cn(
                'group bg-card focus-visible:ring-ring relative flex flex-col overflow-hidden rounded-3xl outline-none focus-visible:ring-2',
                featured &&
                    'min-h-[24rem] justify-end text-white sm:col-span-2 lg:row-span-2 lg:min-h-0',
            )}
            data-test="showcase-card"
        >
            {featured ? (
                <>
                    {visual}
                    <div
                        className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-transparent"
                        aria-hidden="true"
                    />
                    {dateTile}
                    <div className="relative space-y-3 p-6 sm:p-8">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight text-balance sm:text-3xl">
                                {event.name}
                            </h2>
                            <p className="mt-1 text-white/75">
                                {t('showcase.card.by', {
                                    organisation: event.organisationName,
                                })}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-white/80">
                            <span className="capitalize">{when}</span>
                            <span className="text-white">{cta}</span>
                        </div>
                    </div>
                </>
            ) : (
                <>
                    <div className="relative aspect-[16/10] overflow-hidden">
                        {visual}
                        {dateTile}
                    </div>
                    <div className="flex flex-1 flex-col gap-4 p-5">
                        <div>
                            <h2 className="line-clamp-2 text-lg font-semibold tracking-tight">
                                {event.name}
                            </h2>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {t('showcase.card.by', {
                                    organisation: event.organisationName,
                                })}
                            </p>
                        </div>
                        <div className="text-muted-foreground mt-auto flex items-center justify-between gap-3 text-sm">
                            <span className="capitalize">{when}</span>
                            <span className="text-foreground">{cta}</span>
                        </div>
                    </div>
                </>
            )}
        </motion.a>
    );
}
